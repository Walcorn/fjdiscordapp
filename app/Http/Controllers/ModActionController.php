<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ModAction;
use App\ModActionNote;
use App\FJContent;
use App\FunnyjunkUser;

use App\Exceptions\ModActionParseErrorException;
use Storage;
use Carbon\Carbon;
use DB;
use Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Posttwo\FunnyJunk\FunnyJunk;

class ModActionController extends Controller
{

    public function getContentById(FJContent $fjcontent)
    {
        $contents[] = $fjcontent;
        $meta['showHeader'] = false; //@TODO IMPLEMENT
        $meta['showRangePicker'] = false;
        return view('moderator.modaction')->with('contents', $contents)->with('meta', $meta);
    }
    
    public function getContentAttributedToUser($fjusername, $from = null, $to = null)
    {
        if($fjusername == "self")
        {
            $fjusername = Auth::user()->fjuser->username;
        }
        $fjuser = FunnyjunkUser::where('username', $fjusername)->firstOrFail();
        if($from == null && $to == null)
        {
            $lastTimeRated = $this->getLastTimeUserRatedContent($fjuser);
            $meta['lastTimeRated'] = $lastTimeRated->copy();
            $to = $lastTimeRated->copy()->addHour();
            $from = $lastTimeRated->copy()->subDay()->subHour();
        }
        else
        {
            $from = Carbon::parse($from);
            $to = Carbon::parse($to);
            //dd($from, $to);
        }
        //Get available users
        $availableUser = FunnyjunkUser::remember(240)->has('modaction')->get();
        $contents = FJContent::with('modaction')
                    ->with('modaction.notes')
                    ->with('user')
                    ->with('modaction.user')
                    ->where('attributedTo', $fjuser->fj_id)
                    ->whereBetween('created_at', [$from, $to])
                    ->orderBy('id', 'desc')
                    ->get();
        $meta['fjusername'] = $fjusername;
        $meta['showHeader'] = true;
        $meta['from'] = $from ?? "NO RANGE";
        $meta['to'] = $to ?? "SHOWING 24";
        $meta['user'] = $fjuser->username;
        $meta['count'] = $contents->count();
        $meta['availableUsers'] = $availableUser;
        $meta['showRangePicker'] = true;
        return view('moderator.modaction')->with('contents', $contents)->with('meta', $meta);
    }

    protected function getLastTimeUserRatedContent(FunnyjunkUser $user)
    {
        $action = $user->modaction()->orderBy('id', 'desc')->whereIn('category', ['category', 'pc_level', 'skin_level'])->firstOrFail();
        return $action->date;
    }

    public function getContentWithNoAttribution()
    {
        $contents = FJContent::with('modaction')
                    ->with('modaction.notes')
                    ->with('user')
                    ->with('modaction.user')
                    ->where('attributedTo', null)
                    ->get();

        $meta['showHeader'] = true;
        $meta['from'] = Carbon::now()->subDay();
        $meta['to'] = Carbon::now();
        $meta['user'] = "Pending Ratings";
        $meta['count'] = $contents->count();
        $meta['availableUsers'] = FunnyjunkUser::remember(240)->has('modaction')->get();
        $meta['showRangePicker'] = false;
        return view('moderator.modaction')->with('contents', $contents)->with('meta', $meta);

    }

    public function attributeContent(FJContent $content, $userid)
    {
        $content->attributedTo = $userid;
        $content->save();
        $content->modaction->first()->addNote('content_attribute', Auth::user()->fjuser->username . ' attributed content to ' . $userid);
    }


    /*
    * Updates records with new times
    * Remove next update
    */
    public function updateRecords()
    {
        $this->fj = new FunnyJunk();
        $this->fj->login(env("FJ_USERNAME"), env("FJ_PASSWORD"));
        $input = $this->fj->getFlags();
        $input = json_decode($input, true);
        $input = collect($input)->map(function($row){
            return collect($row);
        });

        DB::transaction(function () use($input){
            foreach($input->chunk(1) as $chunk)
            {
                $chunk = $chunk->first();
                if($chunk->get('reference_type') == 'content')
                {
                    try{
                        $content = FJContent::findOrFail($chunk->get('reference_id'));
                        $content->created_at = $chunk->get('date');
                        $content->updated_at = $chunk->get('date');
                        $content->save();
                    } catch(ModelNotFoundException $e)
                    {   
                        echo "New Content Skipped";
                    }
                }
            }
        });
    }
    public function parseJson()
    {
        \Log::info('Parsing JSON');
        $this->fj = new FunnyJunk();
        $this->fj->login(env("FJ_USERNAME"), env("FJ_PASSWORD"));
        $input = $this->fj->getFlags();
        //$input = Storage::disk('local')->get('testing.json'); //DEV
        $latest = ModAction::whereRaw('id = (select max(`id`) from mod_actions)')->first();
        $input = json_decode($input, true);
        $input = collect($input)->map(function($row){
            return collect($row);
        });
        $input = $input->filter(function($value,$key) use ($latest){
            if($value["id"] > $latest->id)
            {
                return true;
            }
            else
            {
                return false;
            }
        });
        $input = $input->reverse();
        //need get latest and discard any old ones to optimise this crap lol
        DB::transaction(function () use($input){
            foreach($input->chunk(1) as $chunk)
            {  
                $chunk = $chunk->first();
                //if($chunk->get('reference_id') != 6713502)
                    //break;

                $action = ModAction::create($chunk->toArray());
                
                //Insert $chunk into mod table thing
                if($chunk->get('reference_type') == 'content')
                {
                    //ACTION RELATING TO CONTENT
                    try{
                        $content = FJContent::findOrFail($chunk->get('reference_id'));
                        $content->updated_at = $action->date;
                    } catch(ModelNotFoundException $e)
                    {   
                        $content = new FJContent;
                        $content->created_at = $action->date;
                    }
                    
                        
                    $content->id = $chunk->get('reference_id');
                    $content->url = $chunk->get('url');
                    $content->fullsize_image = $chunk->get('fullsize_image');
                    $content->thumbnail = $chunk->get('thumbnail');
                    $content->in_nsfw = $chunk->get('in_nsfw');
                    $content->flagged = $chunk->get('flagged');
                    $content->owner = $chunk->get('owner');
                    $content->title = $chunk->get('title');
                    $content->flagged = $chunk->get('flagged');

                    $isActuallyContributing = false;
                    switch($chunk->get('category')){
                        case 'pc_level':
                            $level = $this->getLevelFromString($chunk->get('info'));
                            if($content->rating_pc != $level)
                                $isActuallyContributing = true;
                            $content->rating_pc = $level;
                            break;
                        case 'skin_level':
                            $level = $this->getLevelFromString($chunk->get('info'));
                            if($content->rating_skin != $level)
                                $isActuallyContributing = true;
                            $content->rating_skin = $level;
                            break;
                        case 'category': //rating_category
                            $category = $this->getCategoryFromString($chunk->get('info'));
                            if($content->rating_category != $category)
                                $isActuallyContributing = true;
                            $content->rating_category = $category;
                            break;
                        case 'flag':
                            $isActuallyContributing = true;
                            $flag = $this->getLastWordFromString($chunk->get('info'));
                            $content->flagged_as = $flag;
                            break;
                        case 'unflag':
                            $isActuallyContributing = true;
                            $content->flagged_as = null;
                            $content->hasIssue = true;
                            $action->addNote('fjmeme_parser_message', 'Issue raised due to content unflag');
                            break;
                    }

                    if($content->exists == false)
                        $content->attributedTo = $chunk->get('user_id');
                    else{
                        if($content->attributedTo != $chunk->get('user_id')){
                            if($isActuallyContributing)
                            {
                                $content->attributedTo = null;
                                $action->addNote('fjmeme_parser_message', 'Attribution removed due to moderator conflict');
                            }
                        }
                    }

                    $content->save();
                }
            }
        });


        return("DONE");
    }
    
    protected function getLevelFromString($string)
    {
        return (int) filter_var(($string), FILTER_SANITIZE_NUMBER_INT);
    }

    protected function getCategoryFromString($string)
    {
        if(preg_match('/"([^"]+)"/', $string, $result))
            return $result[1];
        else{
            throw new ModActionParseErrorException("CATEGORY Parsing Error " . $string);
        }
    }

    protected function getLastWordFromString($string)
    {
        $pieces = explode(' ', $string);
        $last_word = array_pop($pieces);
        return $last_word;
    }
}
