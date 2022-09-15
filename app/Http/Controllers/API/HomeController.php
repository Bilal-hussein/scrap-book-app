<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use App\Models\UserDevice;
use App\Models\Password;
use App\Models\Reminder;
use App\Models\Picture;
use App\Models\Friend;
use App\Models\Video;
use App\Models\Voice;
use App\Models\Audio;
use App\Models\Note;
use App\Models\User;
use Carbon\Carbon;
use stdClass;

class HomeController extends Controller
{

    public function users($id, Request $request)
    {
        $friends = Friend::where('user_id', $id)->where('accepted', 1)->pluck('friend_id');
        $friends1 = Friend::where('friend_id', $id)->where('accepted', 1)->pluck('user_id');
        // $ids[] = $id;
        $users = User::whereNotIn('id', [$id]);
        if ($request->keyword)
            $users = $users->where('name', 'LIKE', "%{$request->keyword}%");
        $users = $users->Paginate(20);
        foreach ($users as $user) {
            $friends = Friend::where('user_id', $id)->where('friend_id', $user->id)->orWhere('friend_id', $id)->where('user_id', $user->id)->first();
            if ($friends) {
                if ($friends->friend_id == $id && $friends->accepted == 1) {
                    $user->friend = true;
                    $user->request = false;
                    $user->you_sent = false;
                } elseif ($friends->user_id == $id && $friends->accepted == 1) {
                    $user->friend = true;
                    $user->request = false;
                    $user->you_sent = false;
                } else {
                    $user->friend = false;
                    $user->request = true;
                    if ($friends->user_id == $id)
                        $user->you_sent = true;
                }
            } else {
                $user->friend = false;
                $user->request = false;
                $user->you_sent = false;
            }
        }
        return response(['status' => true, 'data' => $users, 'action' => 'List of users']);
    }

    public function addFriend(Request $request)
    {
        $reciever = User::find($request->friend_id);
        $sender = User::find($request->user_id);
        if ($reciever) {
            /*$friendCheck = Friend::where('friend_id', $reciever->id)->where('user_id', $sender->id)->where('accepted', 1)->first();
            if ($friendCheck) {
                $friendCheck->delete();
                return response()->json([
                    'status' => true,
                    'action' => 'User unfriended'
                ]);
            }*/
            $userCheck = Friend::where('friend_id', $reciever->id)->where('user_id', $sender->id)->where('accepted', 0)->first();
            if ($userCheck) {
                $userCheck->delete();
                return response()->json([
                    'status' => false,
                    'action' => 'Request cancelled'
                ]);
            } else {
                $friend = new Friend();
                $friend->friend_id = $reciever->id;
                $friend->user_id = $sender->id;
                $friend->save();
                $tokens = UserDevice::where('user_id', $reciever->id)->where('fcm_token', '!=', '')->pluck('fcm_token');
                app('App\Http\Controllers\API\AuthController')->pushnotification($tokens, $sender->name . ' sent a friend request', 'Tap on to see all requests', ['status' => 1]);
                return response()->json([
                    'status' => true,
                    'action' => 'Friend saved. Request sent'
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'action' => 'User not exists'
            ]);
        }
    }

    public function unFriend(Request $request)
    {
        $reciever = User::find($request->friend_id);
        $sender = User::find($request->user_id);
        if ($reciever) {
            $friendCheck = Friend::where('friend_id', $reciever->id)->where('user_id', $sender->id)->where('accepted', 1)->orWhere('friend_id', $sender->id)->where('user_id', $reciever->id)->where('accepted', 1)->first();
            if ($friendCheck) {
                $friendCheck->delete();
                return response()->json([
                    'status' => true,
                    'action' => 'User unfriended'
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'action' => 'User is not your friend'
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'action' => 'User not exists'
            ]);
        }
    }

    public function acceptRequest(Request $request)
    {
        $sender = User::find($request->user_id);
        $reciever = User::find($request->friend_id);
        $friend = Friend::where('user_id', $reciever->id)->where('friend_id', $sender->id)->first();

        if ($friend) {
            $friend->accepted = $request->accepted;
            $friend->save();
            if ($request->accepted == 1) {
                $tokens = UserDevice::where('user_id', $reciever->id)->where('fcm_token', '!=', '')->pluck('fcm_token');
                app('App\Http\Controllers\API\AuthController')->pushnotification($tokens, $sender->name . ' accepted your friend request', 'Tap on to see profile', ['status' => 8]);
                return response()->json([
                    'status' => true,
                    'action' => 'Request accepted'
                ]);
            } else {
                $friend = Friend::where('user_id', $reciever->id)->where('friend_id', $sender->id)->delete();
                return response()->json([
                    'status' => true,
                    'action' => 'Request rejected'
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'action' => 'Request not exists'
            ]);
        }
    }

    public function friendsList($id)
    {
        $get = User::find($id);
        if ($get) {
            $friends = Friend::where('user_id', $id)->where('accepted', 1)->pluck('friend_id');
            $friends1 = Friend::where('friend_id', $id)->where('accepted', 1)->pluck('user_id');
            $ids = $friends->merge($friends1);
            $users = User::whereIn('id', $ids)->get();
            return response()->json([
                'status' => true,
                'data'   => $users,
                'action' => 'List of my friends'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'data'   => new stdclass(),
                'action' => 'user not exists'
            ]);
        }
    }

    public function requestsList($id)
    {
        $ids = Friend::where('friend_id', $id)->where('accepted', 0)->pluck('user_id');
        $requests = User::whereIn('id', $ids)->get();
        return response()->json([
            'status' => true,
            'data'   => $requests,
            'action' => 'List of requests'
        ]);
    }

    public function addNote(Request $request)
    {
        $user = User::find($request->user);
        $recipient = User::find($request->recipient_id);
        /*$userData = $request->input();
        $rules = [
            'title' => 'required|max:20',
            'description' => 'required|max:255'
        ];

        $validator = Validator::make($userData, $rules);
        $error = $validator->errors();
        if ($validator->fails())
            return response()->json(['status' => false, 'data' => $error->all(), 'action' => 'Validation errors']);

        else {*/
        foreach ($request->title as $index => $title) {
            $note = new Note();
            $note->title = $title;
            $note->description = $request['description'][$index];
            $note->user_id = $user->id;
            $note->recipient_id = $request->recipient_id;
            $note->date = date_format(date_create($request->date), 'Y-m-d');
            $note->time = date_format(date_create($request->time), 'H:i');
            $note->save();
        }
        $tokens = UserDevice::where('user_id', $recipient->id)->where('fcm_token', '!=', '')->pluck('fcm_token');
        app('App\Http\Controllers\API\AuthController')->pushnotification($tokens, $user->name . ' has sent you some notes', 'Tap on to see all notes', ['status' => 2]);
        return response()->json([
            'status' => true,
            'action' => 'Notes created'
        ]);
        // }
    }

    public function shareNote(Request $request)
    {
        $note = Note::where('user_id', $request->user_id)->first();
        $recipient = User::find($request->recipient_id);
        if ($recipient) {
            $note->shared = 1;
            $note->recipient_id = $recipient->id;
            // $note->date = Carbon::createFromFormat('Y-m-d', $request['date']);
            // $note->time = Carbon::parse($request['time'])->format('h:i a');
            $note->save();

            return response()->json([
                'status' => true,
                'data'   => $note,
                'action' => 'Note shared'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'data'   => new stdClass(),
                'action' => 'Recipient not exists'
            ]);
        }
    }

    public function myNotes($id)
    {
        $notes = Note::select('user_id', 'recipient_id', 'date', 'time')->where('user_id', $id)->distinct()->get();
        foreach ($notes as $note) {
            $note->notes = Note::select('id', 'title', 'description')->where('user_id', $id)->where('recipient_id', $note->recipient_id)->whereDate('date', $note->date)->where('time', $note->time)->distinct()->get();
            $note->recipient = User::find($note->recipient_id);
            $note->user = User::find($note->user_id);
        }
        return response()->json(['status' => true, 'data' => $notes, 'action' => 'My notes']);
    }

    public function sharedNotes($id)
    {
        $notes = Note::where('recipient_id', $id)->where('shared', 1)->get();
        if ($notes) {
            return response()->json([
                'status' => true,
                'data'   => $notes,
                'action' => 'List of shared notes'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'data'   => new stdClass(),
                'action' => 'Recipient not exists'
            ]);
        }
    }

    public function shareFiles(Request $request)
    {
        $userData = $request->input();
        $rules = [
            'date'  => 'required',
            'time'  => 'required',
            'type'  => 'required'
        ];
        $validator = Validator::make($userData, $rules);
        $error = $validator->errors();
        if ($validator->fails())
            return response()->json(['status' => false, 'data' => $error->all(), 'action' => 'Validation errors']);
        else {
            $user = User::find($request->user_id);
            $arr = [];
            $files = $request->media;
            foreach ($files as $index => $file) {
                $path = 'uploads/' . $request->type . '/' . $user->id . '/';
                $filename = $request->type . '-' . $index . uniqid() . '-' . time() . '.' . $file->getClientOriginalExtension();
                if ($file->move($path, $filename))
                    $arr[] = $path . $filename;
            }
            foreach (explode(',', $request->recipient_id) as $recipient_id) {
                $recipient = User::find($recipient_id);
                if ($recipient) {
                    /*if ($user == $recipient) {
                        return response()->json([
                            'status' => false,
                            'data'   => new stdClass,
                            'action' => 'can not send file to yourself'
                        ]);
                    } else */
                    $tokens = UserDevice::where('user_id', $recipient->id)->where('fcm_token', '!=', '')->pluck('fcm_token');
                    if ($user != $recipient) {
                        if ($request->type == 'picture') {
                            $picture = new Picture();
                            $picture->user_id = $user->id;
                            $picture->recipient_id = $recipient->id;
                            $picture->date = date_format(date_create($request['date']), 'Y-m-d');
                            $picture->time = date_format(date_create($request['time']), 'H:i');
                            $picture->picture = json_encode($arr);
                            $picture->save();
                            app('App\Http\Controllers\API\AuthController')->pushnotification($tokens, $user->name . ' has sent you pictures', 'Tap on to see all pictures', ['status' => 3]);
                        }
                        if ($request->type == 'audio') {
                            $userData = $request->input();
                            $rules = [
                                'singer'  => 'required',
                            ];

                            $validator = Validator::make($userData, $rules);
                            $error = $validator->errors();
                            if ($validator->fails())
                                return response()->json(['status' => false, 'data' => $error->all(), 'action' => 'Validation errors']);
                            else
                                $audio = new Audio();
                            $audio->user_id = $user->id;
                            $audio->recipient_id = $recipient->id;
                            $audio->singer = $request['singer'];
                            $audio->date = date_format(date_create($request['date']), 'Y-m-d');
                            $audio->time = date_format(date_create($request['time']), 'H:i');
                            $audio->audio = json_encode($arr);
                            $audio->save();
                            app('App\Http\Controllers\API\AuthController')->pushnotification($tokens, $user->name . ' has sent you music', 'Tap on to see all music', ['status' => 4]);
                        }
                        if ($request->type == 'video') {
                            $video = new Video();
                            $video->user_id = $user->id;
                            $video->recipient_id = $recipient->id;
                            $video->date = date_format(date_create($request['date']), 'Y-m-d');
                            $video->time = date_format(date_create($request['time']), 'H:i');
                            $video->video = json_encode($arr);
                            $video->save();
                            app('App\Http\Controllers\API\AuthController')->pushnotification($tokens, $user->name . ' has sent you videos', 'Tap on to see all videos', ['status' => 5]);
                        }
                        if ($request->type == 'voice') {
                            $voice = new Voice();
                            if ($request->duration)
                                $voice->duration = $request->duration;
                            $voice->user_id = $user->id;
                            $voice->recipient_id = $recipient->id;
                            $voice->date = date_format(date_create($request['date']), 'Y-m-d');
                            $voice->time = date_format(date_create($request['time']), 'H:i');
                            $voice->voice = json_encode($arr);
                            $voice->save();
                            app('App\Http\Controllers\API\AuthController')->pushnotification($tokens, $user->name . ' has sent you voice notes', 'Tap on to see all voice notes', ['status' => 6]);
                        }
                    }
                }
                /*else {
                    return response()->json([
                        'status' => false,
                        'data'   => new stdClass(),
                        'action' => 'Recipient not exists'
                    ]);
                }*/
            }

            return response()->json([
                'status' => true,
                'action' => 'File shared'
            ]);
        }
    }

    public function shared($type, $id)
    {
        $media = new stdClass();
        if ($type == 'picture') {
            /*if ($status == 'sent')
                $dates = Picture::select('date')->where('user_id', $id)->distinct('date')->Paginate(10);

            if ($status == 'received')
                $dates = Picture::select('date')->where('recipient_id', $id)->distinct('date')->Paginate(10);

            foreach ($dates as $date) {
                $times  = Picture::select('time');
                if ($status == 'sent')
                    $times = $times->where('user_id', $id)->whereDate('date', $date->date)->distinct('time')->pluck('time');
                if ($status == 'received')
                    $times = $times->where('recipient_id', $id)->whereDate('date', $date->date)->distinct('time')->pluck('time');
                foreach ($times as $time) {
                    $obj = new stdClass();
                    $obj->date = $date->date;
                    $obj->time = $time;
                    $data =  Picture::select('id', 'user_id', 'recipient_id', 'picture AS media')->where('date', $date->date)->where('time', $time)->latest()->get();
                    foreach ($data as $item) {
                        $item->user = User::find($item->user_id);
                        $item->recipient = User::find($item->recipient_id);
                    }
                    $obj->media = $data;
                    $media = $obj;
                }
            }*/
            $dates = Picture::select('date')->where('user_id', $id)->distinct('date')->orWhere('recipient_id', $id)->distinct('date')->pluck('date')->toArray();
            $times  = Picture::select('time')->where('user_id', $id)->whereIn('date', $dates)->distinct('time')->orWhere('recipient_id', $id)->whereIn('date', $dates)->distinct('time')->pluck('time');
            $data =  Picture::select('id', 'user_id', 'recipient_id', 'date', 'time', 'picture AS media')->whereIn('date', $dates)->whereIn('time', $times)->latest()->Paginate(12);
            foreach ($data as $item) {
                $user = User::find($item->user_id);
                $recipient = User::find($item->recipient_id);
                $item->user = $user;
                $item->recipient = $recipient;
                $item->user_name = $user->name;
                $item->recipient_name = $recipient->name;
            }
            $media = $data;
        }
        if ($type == 'audio') {
            $dates = Audio::select('date')->where('user_id', $id)->distinct('date')->orWhere('recipient_id', $id)->distinct('date')->pluck('date')->toArray();
            $times  = Audio::select('time')->where('user_id', $id)->whereIn('date', $dates)->distinct('time')->orWhere('recipient_id', $id)->whereIn('date', $dates)->distinct('time')->pluck('time');
            $data =  Audio::select('id', 'user_id', 'recipient_id', 'date', 'time', 'audio AS media', 'singer')->whereIn('date', $dates)->whereIn('time', $times)->latest()->Paginate(12);
            foreach ($data as $item) {
                $item->user = User::find($item->user_id);
                $item->recipient = User::find($item->recipient_id);
            }
            $media = $data;
        }
        if ($type == 'video') {
            $dates = Video::select('date')->where('user_id', $id)->distinct('date')->orWhere('recipient_id', $id)->distinct('date')->pluck('date')->toArray();
            $times  = Video::select('time')->where('user_id', $id)->whereIn('date', $dates)->distinct('time')->orWhere('recipient_id', $id)->whereIn('date', $dates)->distinct('time')->pluck('time');
            $data =  Video::select('id', 'user_id', 'recipient_id', 'date', 'time', 'video AS media')->whereIn('date', $dates)->whereIn('time', $times)->latest()->Paginate(12);
            foreach ($data as $item) {
                $item->user = User::find($item->user_id);
                $item->recipient = User::find($item->recipient_id);
            }
            $media = $data;
        }
        if ($type == 'voice') {
            $dates = Voice::select('date')->where('user_id', $id)->distinct('date')->orWhere('recipient_id', $id)->distinct('date')->pluck('date')->toArray();
            $times  = Voice::select('time')->where('user_id', $id)->whereIn('date', $dates)->distinct('time')->orWhere('recipient_id', $id)->whereIn('date', $dates)->distinct('time')->pluck('time');
            $data =  Voice::select('id', 'user_id', 'recipient_id', 'date', 'time', 'voice AS media')->whereIn('date', $dates)->whereIn('time', $times)->latest()->Paginate(12);
            foreach ($data as $item) {
                $item->user = User::find($item->user_id);
                $item->recipient = User::find($item->recipient_id);
            }
            $media = $data;
        }
        return response()->json([
            'status' => true,
            'data'   => $media,
            'action' => 'List of shared ' . $type
        ]);
    }

    public function deleteFiles($type, $id)
    {
        $status = false;
        $text = '';
        if ($type == 'picture') {
            $find = Picture::find($id);
            if ($find) {
                $media = explode(',', $find->picture);
                foreach ($media as $item) {
                    if (File::exists(public_path($item)))
                        File::delete(public_path($item));
                }
                $find->delete();
                $status = true;
                $text = 'Picture removed';
            } else {
                $text = 'No picture found of this identity';
            }
        } elseif ($type == 'audio') {
            $find = Audio::find($id);
            if ($find) {
                $media = explode(',', $find->audio);
                foreach ($media as $item) {
                    if (File::exists(public_path($item)))
                        File::delete(public_path($item));
                }
                $find->delete();
                $status = true;
                $text = 'Audio removed';
            } else {
                $text = 'No audio found of this identity';
            }
        } elseif ($type == 'video') {
            $find = Video::find($id);
            if ($find) {
                $media = explode(',', $find->video);
                foreach ($media as $item) {
                    if (File::exists(public_path($item)))
                        File::delete(public_path($item));
                }
                $find->delete();
                $status = true;
                $text = 'Video removed';
            } else {
                $text = 'No video found of this identity';
            }
        } elseif ($type == 'voice') {
            $find = Voice::find($id);
            if ($find) {
                $media = explode(',', $find->voice);
                foreach ($media as $item) {
                    if (File::exists(public_path($item)))
                        File::delete(public_path($item));
                }
                $find->delete();
                $status = true;
                $text = 'Voice removed';
            } else {
                $text = 'No voice found of this identity';
            }
        } elseif ($type == 'note') {
            $find = Note::find($id);
            if ($find) {
                $find->delete();
                $status = true;
                $text = 'Note removed';
            } else {
                $text = 'No note found of this identity';
            }
        } elseif ($type == 'reminder') {
            $find = Reminder::find($id);
            if ($find) {
                $find->delete();
                $status = true;
                $text = 'Reminder removed';
            } else {
                $text = 'No reminder found of this identity';
            }
        } elseif ($type == 'password') {
            $find = Password::find($id);
            if ($find) {
                $find->delete();
                $status = true;
                $text = 'Password removed';
            } else {
                $text = 'No password found of this identity';
            }
        }
        return response(['status' => $status, 'action' => $text]);
    }

    public function sharedPictures($id)
    {
        $date  = Picture::where('recipient_id', $id)->pluck('date');
        $time  = Picture::where('recipient_id', $id)->pluck('time');
        $media = Picture::whereIn('date', $date)->whereIn('time', $time)->latest()->get();

        return response()->json([
            'status' => true,
            'data'   => $media,
            'action' => 'List of shared pictures'
        ]);
    }

    public function sharedMusic($id)
    {
        $date  = Audio::where('recipient_id', $id)->pluck('date');
        $time  = Audio::where('recipient_id', $id)->pluck('time');
        $media = Audio::whereIn('date', $date)->whereIn('time', $time)->latest()->get();

        return response()->json([
            'status' => true,
            'data'   => $media,
            'action' => 'List of shared Audio files'
        ]);
    }

    public function sharedVideos($id)
    {
        $date  = Video::where('recipient_id', $id)->pluck('date');
        $time  = Video::where('recipient_id', $id)->pluck('time');
        $media = Video::whereIn('date', $date)->whereIn('time', $time)->latest()->get();

        return response()->json([
            'status' => true,
            'data'   => $media,
            'action' => 'List of shared Video files'
        ]);
    }

    public function sharedVoices($id)
    {
        $date  = Voice::where('recipient_id', $id)->pluck('date');
        $time  = Voice::where('recipient_id', $id)->pluck('time');
        $media = Voice::whereIn('date', $date)->whereIn('time', $time)->latest()->get();

        return response()->json([
            'status' => true,
            'data'   => $media,
            'action' => 'List of shared Voice mails'
        ]);
    }

    public function saveReminder(Request $request)
    {
        $user = User::find($request->user);
        $today = Carbon::today();
        $userData = $request->input();
        $rules = [
            'remind_date' => 'required',
            'remind_time' => 'required',
        ];

        $validator = Validator::make($userData, $rules);
        $error = $validator->errors();
        if ($validator->fails())
            return response()->json(['status' => false, 'data' => $error->all(), 'action' => 'Validation errors']);
        else {

            if ($user) {
                $reminder = new Reminder();
                $reminder->user_id = $user->id;
                $reminder->user_name = $user->name;
                $reminder->title = $request->title;
                $reminder->description = $request->description;
                $reminder->remind_date = date_format(date_create($request['remind_date']), 'Y-m-d');
                if ($reminder->remind_date < $today) {
                    return response()->json(['status' => false, 'data' => new stdClass(), 'action' => 'Can not input past date']);
                }
                $reminder->remind_time = Carbon::parse($request['remind_time'])->format('h:i a');
                $reminder->save();

                return response()->json([
                    'status' => true,
                    'data'   => $reminder,
                    'action' => 'Reminder saved'
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'data'   => new stdClass(),
                    'action' => 'user not exists'
                ]);
            }
        }
    }

    public function reminderCron()
    {
        $users = [];
        foreach ($users as $user) {
            $tokens = UserDevice::where('user_id', $user->id)->where('fcm_token', '!=', '')->pluck('fcm_token');
            app('App\Http\Controllers\API\AuthController')->pushnotification($tokens, 'You have a reminder at this time', 'Tap on to see your reminder', ['status' => 7]);
        }
    }

    public function sendReminder(Request $request)
    {
        $userData = $request->input();
        $rules = [
            'date'  => 'required',
            'time'  => 'required',
        ];

        $validator = Validator::make($userData, $rules);
        $error = $validator->errors();
        if ($validator->fails())
            return response()->json(['status' => false, 'data' => $error->all(), 'action' => 'Validation errors']);
        else {
            $user = User::find($request->user_id);
            $recipient = User::find($request->recipient_id);
            $reminder = Reminder::where('user_id', $request->user_id)->first();
            if ($recipient) {

                if ($user == $recipient) {
                    return response()->json([
                        'status' => false,
                        'data'   => new stdClass,
                        'action' => 'can not send reminder to yourself'
                    ]);
                } else {
                    $reminder->recipient_id = $recipient->id;
                    $reminder->shared = 1;
                    $reminder->date = date_format(date_create($request['date']), 'Y-m-d');
                    $reminder->time = date_format(date_create($request['time']), 'H:i');
                    $reminder->save();
                    return response()->json([
                        'status' => true,
                        'data'   => $reminder,
                        'action' => 'reminder sent'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'data'   => new stdClass,
                    'action' => 'user not exists'
                ]);
            }
        }
    }

    public function sharedReminders($id)
    {
        $reminders = Reminder::select('id', 'remind_date', 'remind_time', 'title', 'description')->where('user_id', $id)->latest()->Paginate(24);
        return response()->json([
            'status' => true,
            'data'   => $reminders,
            'action' => 'List of Reminders'
        ]);
    }

    public function savePasswords(Request $request)
    {
        $user = User::find($request->user);
        $userData = $request->input();
        $rules = [
            'title'    => 'required|max:255',
            'password' => 'required|min:6'
        ];
        $validator = Validator::make($userData, $rules);
        $error = $validator->errors();
        if ($validator->fails())
            return response()->json([
                'status' => false,
                'errors' => $error->all(),
                'action' => 'validation errors !'
            ]);

        if ($user) {
            $new = new Password();
            $new->user_id = $user->id;
            $new->title = $request->title;
            $new->password = encrypt($request['password']);
            $new->save();

            return response()->json([
                'status' => true,
                'data'   => $new,
                'action' => 'Password saved'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'data'   => new stdClass(),
                'action' => 'user not exists'
            ]);
        }
    }

    public function ViewPassword($id)
    {
        $user = Password::where('id', $id)->first();
        if ($user) {
            $password = new stdClass();
            $password->title = $user->title;
            $password->password = decrypt($user->password);

            return response()->json([
                'status' => true,
                'data'   => $password,
                'action' => 'View Password'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'data'   => new stdClass(),
                'action' => 'user not exists'
            ]);
        }
    }

    public function AllPasswords($id)
    {
        $passwords = Password::where('user_id', $id)->latest()->get();
        if ($passwords)
            return response()->json(['status' => true, 'data' => $passwords, 'action' => 'List of all passwords']);
        else
            return response()->json(['status' => false, 'data' => new stdClass(), 'action' => 'user not exists']);
    }
}
