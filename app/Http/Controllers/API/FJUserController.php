<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Auth;
use App\Role;
use Posttwo\FunnyJunk\FunnyJunk;
use Posttwo\FunnyJunk\User;
use Cache;

class FJUserController extends \App\Http\Controllers\Controller
{
    public function getBasicUserByUsername($username)
    {
        $response = Cache::remember('fjapi.getBasicUserByUsername.' . $username, 60, function() use($username){
            $user = new User();
            $user->set(array('username' => $username));
            $user->populate();

            $response['username'] = $user->username;
            $response['group_name'] = $user->group_name;
            $response['max_level'] = $user->level;
            $response['content_level'] = (int)filter_var($user->rank_info->currentContentLabel, FILTER_SANITIZE_NUMBER_INT);
            $response['comment_level'] = (int)filter_var($user->rank_info->currentCommentLabel, FILTER_SANITIZE_NUMBER_INT);
            return $response;
        });
        
        return $response;
    }
    
    public function getModUserByUsername($username)
    {
        $response = Cache::remember('fjapi.getModUserByUsername.' . $username, 10, function() use($username){
            $user = new User();
            $user->set(array('username' => $username));
            $user->populate();

            $response['username'] = $user->username;
            $response['userId'] = $user->userId;
            $response['joined'] = $user->joined;
            $response['last_online'] = $user->last_online;
            $response['contributor_account'] = $user->contributor_account;
            $response['role_description'] = $user->role_description;
            $response['has_oc_item'] = $user->has_oc_item;
            $response['ban_history'] = $user->ban_history;
            $response['last_online'] = $user->last_online;

            return $response;
        });
        
        return $response;
    }
}
