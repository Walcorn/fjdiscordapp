<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\CAHBot::class,
        Commands\UnverifyUser::class,
        Commands\ClearBotCookie::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //Check new askamod comments
        /*$schedule->call(function () {
            $fj = new \Posttwo\FunnyJunk\FunnyJunk;
			$fj->login(env("FJ_USERNAME"), env("FJ_PASSWORD"));
            $r = $fj->getByUrl("/mod-social");
            $comments = $r->comments;

            $lastProcessedId = \Cache::get("Cron-ModSocial", 0);

            foreach($comments as $com) {
                if($com->id == "empty")
                    continue;
                if($com->is_sticky == 1)
                    continue;
                if($com->reply_level != 0)
                    continue;
                if($lastProcessedId >= $com->id)
                    continue;
                
                $slack = new \App\Slack;
                $slack->target = 'mod-notify';
                $slack->username =   $com->username;
                $slack->avatar   =   $com->original_avatar_url;
                $slack->title    = '';
                $slack->text     = str_limit($com->text, 1500);


                $slack->embedFields = ['Username' => $com->username,
                                       'Text' => str_limit($com->text, 80),
                                       'ID'   => $com->id,
                                       'Date' => $com->date,
                                       'Link' => 'https://funnyjunk.com/mod-social/' . $com->number ];

                \Notification::send($slack, new \App\Notifications\ModNotify(null));
            }
            $collection = collect($comments);
            $collection->pop(); //admin is a retard
            \Cache::forever("Cron-ModSocial", $collection->max('id'));
            
        })->everyFiveMinutes();*/

         //Check mod stats comments
         $schedule->call(function () {
            if(\Cache::get('CRON-rating-reminder', true) == false) return;
            $fj = new \Posttwo\FunnyJunk\FunnyJunk;
            $fj->login(env("FJ_USERNAME"), env("FJ_PASSWORD"));
            $r = $fj->getModInfo();
            $lastCall = \Cache::get("Cron-SFW_COUNT", 0);
            $isIncreasing = $r->sfw > $lastCall;
            //$r -> sfw nsfw links
            if($r->sfw > 30 && $isIncreasing)
            {
                $slack = new \App\Slack;
                $slack->target = 'mod-social';
				$slack->title    = '';
				
                $slack->username =   "Big Sister";
                $slack->avatar   =   'https://i.imgur.com/xEUNwuN.png';
                $slack->text     = '<@&427487027429244929>  Rate or no bonewhip for you!';
                
				if($r->sfw > 40){
					$slack->username =   "The rating doge";
					$slack->avatar   =   'https://i.imgur.com/HeGEEbu.jpg';
					$slack->text     = 	 "<:OkRetard1:625111286887219200><:OkRetard2:625111299713400832>\n<:OkRetard3:625111313688821760><:OkRetard4:625111326091640833> <@&427487027429244929> wow, much moderators, many rate, very content! ";
				}
				
				if($r->sfw > 50){
					$slack->username =   "🅱️osttwo";
					$slack->avatar   =   'https://i.imgur.com/bV0pD76.png';
                    			$slack->text     =   'Mmmmmmm Im pissing and shidding and farding and cumming on you until you rate <@&305827361767817216> <@&137342300723478528> <@&151904749984284672> **Students with a cap above 20 are allowed ignore their cap and rate until all content is rated**';
				}
				
				if($r->sfw > 70){
					$slack->username = "Helpfuls Ressurection";
					$slack->avatar   = 'https://i.imgur.com/EyptcJP.png';
					$slack->text     = '@everyone The needs of the many outweigh the needs of the few. Remodding <@156717038570700800> in progress';
				}
				
				if($r->sfw > 100){
					$slack->username = "Trap Cock Recruitment";
					$slack->avatar   = 'https://i.imgur.com/8Gn5cs7.png';
					$slack->text     = 'Requesting penis until all rates have been completed.';
				}
                
				$slack->embedFields = [ 'SFW' => $r->sfw,
                                        'NSFW' => $r->nsfw,
                                        'LINKS'   => $r->links ];

                \Notification::send($slack, new \App\Notifications\ModNotify(null));
            }
            \Cache::forever("Cron-SFW_COUNT", $r->sfw);
            
        })->everyFiveMinutes();
        
        $schedule->call(function () {
            if(\Cache::get('CRON-sameip', true) == false) return;
            $fj = new \Posttwo\FunnyJunk\FunnyJunk;
            $user = new \Posttwo\FunnyJunk\User;
            $fj->login(env("FJ_USERNAME"), env("FJ_PASSWORD"));
            $user->id = 1490859;
            $accounts = $user->getUsersSameId();
            $previous = \Cache::get("SAMEIP-1490859", []);
            $current = [];
            foreach($accounts as $ac){
                    $current[] = $ac->username;
            }
            $diff = array_diff($current, $previous);
            $total = array_merge($previous, $diff);
            \Cache::forever("SAMEIP-1490859", $total);      
            if($diff != [])
            {
                $text = '';
                foreach($diff as $d)
                {
                    $text .= ' ' . $d;
                }
                $slack = new \App\Slack;
                $slack->target = 'mod-social';
                $slack->username =   "Anitas Butthole";
                $slack->text     =   null;
                $slack->avatar   =   'https://i.imgur.com/rwFxHPc.png';
                $slack->title    = '';
                $slack->text     =  '<@&151904333703675904> I found illegal accounts! ' . $text;
                $slack->embedFields = ['Monitored User' => 1490859];

                \Notification::send($slack, new \App\Notifications\ModNotify(null));
            }
        })->everyTenMinutes();

        $schedule->call(function () {
            if(\Cache::get('CRON-remind-user-flagged', true) == false) return;
            $slack = new \App\Slack;
            $slack->target = 'mod-notify';
            $slack->username = 'KillinTime';
            $slack->avatar = 'https://i.imgur.com/cVsdYOH.png';
            $slack->title = "Title Test";
            $slack->text = 'PLEASE CHECK USER FLAGGED COMMENTS <@&551354445221593089>';
            $slack->color = "error";
            \Notification::send($slack, new \App\Notifications\ModNotifyNew(null));
            
        })->everyFifteenMinutes();

        $schedule->call(function () {
            if(\Cache::get('CRON-remind-hunter-hourly', true) == false) return;
            $slack = new \App\Slack;
            $slack->target = 'mod-notify';
            $slack->username = 'Hunter Weapon';
            $slack->avatar = 'https://i.imgur.com/RyPN677.png';
            $slack->title = "Title Test";
            $slack->text = 'STOP STANDING IN FIRE <@&497478544897605652>';
            $slack->color = "error";
            \Notification::send($slack, new \App\Notifications\ModNotifyNew(null));
        
        })->hourly();

        $schedule->call('App\Http\Controllers\ModActionController@parseJson')->hourly();
        $schedule->call(function(){
            if(\Cache::get('CRON-grab-new-complaints', true) == false) return;
            $x = new \App\ModCase;
            $x->bulkImport();
        })->everyFiveMinutes();

        $schedule->call(function(){
            if(\Cache::get('CRON-grab-new-user-flags', true) == false) return;
            $x = new \App\UserFlag;
            $x->bulkImport();
        })->everyFiveMinutes();

    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
    }
}
