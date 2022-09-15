<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\RecoverPassword;
use Illuminate\Http\Request;
use App\Mail\VerifyAccount;
use App\Models\UserDevice;
use App\Models\Reminder;
use App\Models\Password;
use App\Models\Picture;
use App\Models\Friend;
use App\Models\Audio;
use App\Models\Video;
use App\Models\Voice;
use App\Models\Note;
use App\Models\User;
use stdClass;

class AuthController extends Controller
{
    public function signUp(Request $request)
    {
        $device = $request->device;
        $deviceName = $request->device_name;
        $token = $request->token;
        $userData = $request->input();
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'nullable|min:6'
        ];

        $validator = Validator::make($userData, $rules);
        $error = $validator->errors();

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $error->all(),
                'action' => 'Account Signup failure'
            ]);
        } else {
            $platform = '';
            if ($request->platform)
                $platform = $request->platform;
            $email = User::where('email', $request['email'])->where('platform', $platform)->first();
            if ($email) {
                if ($platform != '') {
                    $token = explode('|', $email->createToken('API AUTH TOKEN')->plainTextToken)[1];
                    return response()->json([
                        'status' => true,
                        'action' => 'user details',
                        'data'   => $email,
                        'access_token' => $token,
                    ]);
                } else {
                    return response()->json([
                        'status' => false,
                        'data'   => new stdClass(),
                        'action' => 'Email already exists'
                    ]);
                }
            } else {
                $user = new User();
                $user->name = $userData['name'];
                $user->email = $userData['email'];
                if ($request->platform) {
                    $user->platform = $request->platform;
                    $user->password = '';
                } else
                    $user->password = Hash::make($userData['password']);
                $user->save();
                if ($device && $deviceName && $token)
                    $this->updateToken($user->id, $device, $deviceName, $token);
                $token = explode('|', $user->createToken('API AUTH TOKEN')->plainTextToken)[1];
                $get = User::find($user->id);

                return response()->json([
                    'status' => true,
                    'data' => $get,
                    'action' => 'Account Signup success !',
                    'access_token' => $token
                ]);
            }
        }
    }

    public function login(Request $request)
    {
        $device = $request->device;
        $deviceName = $request->device_name;
        $token = $request->token;
        $userData = $request->input();
        $rules = [
            'email' => 'required|max:255',
        ];

        $validator = Validator::make($userData, $rules);
        $error = $validator->errors();
        if ($validator->fails())
            return response()->json([
                'status' => false,
                'errors' => $error->all(),
                'action' => 'validation errors !'
            ]);

        $user = User::where('email', $request['email'])->first();
        if ($user) {
            if ($user->platform == '') {

                if (Hash::check($request['password'], $user->password)) {
                    if ($device && $deviceName && $token)
                        $this->updateToken($user->id, $device, $deviceName, $token);
                    $token = explode('|', $user->createToken('API AUTH TOKEN')->plainTextToken)[1];
                    return response()->json([
                        'status' => true,
                        'action' => 'user details',
                        'data'   => $user,
                        'access_token' => $token,
                    ]);
                } else
                    return response()->json([
                        'status' => false,
                        'error'  => [],
                        'action' => 'Password incorrect!'
                    ]);
            } else {
                $token = explode('|', $user->createToken('API AUTH TOKEN')->plainTextToken)[1];
                return response()->json([
                    'status' => true,
                    'action' => 'user details',
                    'data'   => $user,
                    'access_token' => $token,
                ]);
            }
        } else
            return response()->json([
                'status' => false,
                'error'  => [],
                'action' => 'No user found'
            ]);
    }

    public function logout(Request $request)
    {
        $device = UserDevice::where('device_id', $request->device_id)->first();
        $user = User::find($request->user_id);
        if ($user) {
            if ($device) {
                $device->fcm_token = '';
                $device->save();
            }
            if (auth()->user())
                auth()->user()->tokens()->delete();
            return response()->json(['status' => true, 'action' => 'You have successfuly logged out']);
        } else
            return response()->json(['status' => false, 'data' => new stdclass(), 'action' => 'No user found']);
    }

    public function forgotPassword(Request $request)
    {
        if ($userData = $request->input()) {
            $rules = [
                'email' => 'required|string|email',
            ];
        }
        $validator = Validator::make($userData, $rules);
        $error = $validator->errors();
        if ($validator->fails())
            return response()->json(['status' => false, 'code' => 0, 'errors' => $error->all(), 'action' => 'Validation errors']);
        else {
            $user = User::where('email', $request->email)->first();
            if ($user) {
                $code = rand(100000, 999999);
                Mail::to($user->email)->send(new RecoverPassword($code));
                return response()->json(['status' => true, 'code' => $code, 'errors' => [], 'action' => 'Email sent with otp']);
            } else
                return response()->json(['status' => false, 'code' => 0, 'errors' => [], 'action' => 'Email does not exist']);
        }
    }

    public function resetPassword(Request $request)
    {
        if ($userData = $request->input()) {
            $rules = [
                'email' => 'required|string|email|max:255|exists:users',
                'password' => 'required'
            ];
        }
        $validator = Validator::make($userData, $rules,);
        $error = $validator->errors();
        if ($validator->fails())
            return response()->json(['status' => false, 'errors' => $error->all(), 'action' => 'Password reset unsuccessful !']);

        $user = User::where('email', $request['email'])->first();
        $user->password = Hash::make($request->password);
        $user->save();

        return response([
            'status' => true,
            'errors' => [],
            'action' => 'Password reset successfully'
        ]);
    }

    public function changePassword(Request $request)
    {
        if ($userData = $request->input()) {
            $rules = [
                'email' => 'required|string|email|max:255|exists:users',
                'old_password' => 'required',
                'password' => 'required|min:6'
            ];
        }
        $validator = Validator::make($userData, $rules);
        $error = $validator->errors();
        if ($validator->fails())
            return response()->json(['status' => false, 'errors' => $error->all(), 'action' => 'Password change failure !']);

        $user = User::where('email', $request['email'])->first();
        if (Hash::check($request->old_password, $user->password)) {
            $user->password = bcrypt($request->password);
            $user->save();

            return response()->json(['status' => true, 'errors' => [], 'action' => ' Password successfully changed !']);
        } else
            return response()->json(['status' => false, 'errors' => [], 'action' => 'Old Password incorrect !']);
    }

    public function verifyAccount(Request $request)
    {
        if ($userData = $request->input()) {
            $rules = [
                'email' => 'required|string|email',
            ];
        }
        $validator = Validator::make($userData, $rules);
        $error = $validator->errors();
        if ($validator->fails())
            return response()->json(['status' => false, 'code' => 0, 'errors' => $error->all(), 'action' => 'Validation errors']);
        else {
            $user = User::where('platform', '')->where('email', $request->email)->first();
            if ($user)
                return response()->json(['status' => false, 'code' => 0, 'errors' => [], 'action' => 'Email already exists']);
            else {
                $code = rand(100000, 999999);
                Mail::to($request->email)->send(new VerifyAccount($code));
                return response()->json(['status' => true, 'code' => $code, 'errors' => [], 'action' => 'Email sent with otp']);
            }
        }
    }

    public function updateUser(Request $request)
    {
        $user = User::find($request->user);
        if ($user) {
            if ($request->name)
                $user->name = $request->name;
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = $user->id . '-' . time() . $file->getClientOriginalExtension();
                $path = 'uploads/user/profile' . $user->id . '/';
                if ($file->move($path, $filename))
                    $user->image = '/' . $path . $filename;
            }
            if ($request->avatar)
                $user->image = $request->avatar;
            $user->save();
            $token = explode('|', $user->createToken('API AUTH TOKEN')->plainTextToken)[1];
            return response()->json(['status' => true, 'data' => $user, 'access_token' => $token, 'action' => 'User profile updated']);
        } else
            return response()->json(['status' => false, 'data' => new stdClass(), 'access_token' => '', 'action' => 'User not updated']);
    }

    public function addLegacyCode($id)
    {
        $user = User::find($id);
        if ($user) {
            $user->legacy_code = uniqid(str_replace(' ', '-', $user->name));
            $user->save();
            return response(['status' => true, 'data' => $user->legacy_code, 'action' => 'Legacy code updated']);
        } else
            return response(['status' => false, 'data' => '', 'action' => 'User not found']);
    }

    public function findLegacyCode(Request $request)
    {
        $user = User::find($request->user_id);
        if ($user) {
            $check = User::where('legacy_code', $request->code)->first();
            if ($check) {
                $main = [];
                $dates = Picture::select('date')->where('user_id', $check->id)->distinct('date')->orWhere('recipient_id', $check->id)->distinct('date')->pluck('date')->toArray();
                $times  = Picture::select('time')->where('user_id', $check->id)->whereIn('date', $dates)->distinct('time')->orWhere('recipient_id', $check->id)->whereIn('date', $dates)->distinct('time')->pluck('time');
                $data =  Picture::select('id', 'user_id', 'recipient_id', 'date', 'time', 'picture AS media')->whereIn('date', $dates)->whereIn('time', $times)->latest()->get();
                foreach ($data as $item) {
                    $getUser = User::find($item->user_id);
                    $recipient = User::find($item->recipient_id);
                    $item->user = $getUser;
                    $item->recipient = $recipient;
                    $item->user_name = $getUser->name;
                    $item->recipient_name = $recipient->name;
                }
                $aData = new stdClass();
                $aData->type = 'picture';
                $aData->picture = $data;
                $main[] = $aData;
                $dates = Audio::select('date')->where('user_id', $check->id)->distinct('date')->orWhere('recipient_id', $check->id)->distinct('date')->pluck('date')->toArray();
                $times  = Audio::select('time')->where('user_id', $check->id)->whereIn('date', $dates)->distinct('time')->orWhere('recipient_id', $check->id)->whereIn('date', $dates)->distinct('time')->pluck('time');
                $audioData =  Audio::select('id', 'user_id', 'recipient_id', 'date', 'time', 'audio AS media', 'singer')->whereIn('date', $dates)->whereIn('time', $times)->latest()->get();
                foreach ($audioData as $item) {
                    $item->user = User::find($item->user_id);
                    $item->recipient = User::find($item->recipient_id);
                }
                $aData1 = new stdClass();
                $aData1->type = 'audio';
                $aData1->audio = $audioData;
                $main[] = $aData1;
                $dates = Video::select('date')->where('user_id', $check->id)->distinct('date')->orWhere('recipient_id', $check->id)->distinct('date')->pluck('date')->toArray();
                $times  = Video::select('time')->where('user_id', $check->id)->whereIn('date', $dates)->distinct('time')->orWhere('recipient_id', $check->id)->whereIn('date', $dates)->distinct('time')->pluck('time');
                $videoData =  Video::select('id', 'user_id', 'recipient_id', 'date', 'time', 'video AS media')->whereIn('date', $dates)->whereIn('time', $times)->latest()->get();
                foreach ($videoData as $item) {
                    $item->user = User::find($item->user_id);
                    $item->recipient = User::find($item->recipient_id);
                }
                $aData2 = new stdClass();
                $aData2->type = 'video';
                $aData2->video = $videoData;
                $main[] = $aData2;
                $dates = Voice::select('date')->where('user_id', $check->id)->distinct('date')->orWhere('recipient_id', $check->id)->distinct('date')->pluck('date')->toArray();
                $times  = Voice::select('time')->where('user_id', $check->id)->whereIn('date', $dates)->distinct('time')->orWhere('recipient_id', $check->id)->whereIn('date', $dates)->distinct('time')->pluck('time');
                $voiceData =  Voice::select('id', 'user_id', 'recipient_id', 'date', 'time', 'voice AS media')->whereIn('date', $dates)->whereIn('time', $times)->latest()->get();
                foreach ($voiceData as $item) {
                    $item->user = User::find($item->user_id);
                    $item->recipient = User::find($item->recipient_id);
                }
                $aData3 = new stdClass();
                $aData3->type = 'voice';
                $aData3->voice = $voiceData;
                $main[] = $aData3;
                $notes = Note::select('user_id', 'recipient_id', 'date', 'time')->where('user_id', $check->id)->distinct()->get();
                foreach ($notes as $note) {
                    $note->notes = Note::select('id', 'title', 'description')->where('user_id', $check->id)->where('recipient_id', $note->recipient_id)->whereDate('date', $note->date)->where('time', $note->time)->distinct()->get();
                    $note->recipient = User::find($note->recipient_id);
                    $note->user = User::find($note->user_id);
                }
                $aData4 = new stdClass();
                $aData4->type = 'note';
                $aData4->note = $notes;
                $main[] = $aData4;
                $reminders = Reminder::select('id', 'remind_date', 'remind_time', 'title', 'description')->where('user_id', $check->id)->latest()->get();
                $aData5 = new stdClass();
                $aData5->type = 'reminder';
                $aData5->reminder = $reminders;
                $main[] = $aData5;
                $passwords = Password::where('user_id', $check->id)->latest()->get();
                $aData6 = new stdClass();
                $aData6->type = 'password';
                $aData6->password = $passwords;
                $main[] = $aData6;
                $friends = Friend::where('user_id', $check->id)->where('accepted', 1)->pluck('friend_id');
                $friends1 = Friend::where('friend_id', $check->id)->where('accepted', 1)->pluck('user_id');
                $ids = $friends->merge($friends1);
                $friends = User::whereIn('id', $ids)->get();
                $aData7 = new stdClass();
                $aData7->type = 'friend';
                $aData7->friend = $friends;
                $main[] = $aData7;
                return response(['status' => true, 'data' => $main, 'action' => 'Legacy code']);
            } else
                return response(['status' => false, 'data' => array(), 'action' => 'Legacy code not found']);
        } else
            return response(['status' => false, 'data' => array(), 'action' => 'User not found']);
    }

    public function findLegacyCodeV1(Request $request)
    {
        $user = User::find($request->user_id);
        if ($user) {
            $check = User::where('legacy_code', $request->code)->first();
            if ($check) {
                $main = [];
                $dates = Picture::select('date')->where('user_id', $check->id)->distinct('date')->orWhere('recipient_id', $check->id)->distinct('date')->pluck('date')->toArray();
                $times  = Picture::select('time')->where('user_id', $check->id)->whereIn('date', $dates)->distinct('time')->orWhere('recipient_id', $check->id)->whereIn('date', $dates)->distinct('time')->pluck('time');
                $data =  Picture::select('id', 'user_id', 'recipient_id', 'date', 'time', 'picture AS media')->whereIn('date', $dates)->whereIn('time', $times)->latest()->get();
                foreach ($data as $item) {
                    $item->notes = [];
                    $item->title = '';
                    $item->description = '';
                    $item->name = '';
                    $item->image = '';
                    $item->singer = '';
                    $getUser = User::find($item->user_id);
                    $recipient = User::find($item->recipient_id);
                    $item->user = $getUser;
                    $item->recipient = $recipient;
                }
                $aData = new stdClass();
                $aData->type = 'picture';
                $aData->picture = $data;
                $main[] = $aData;
                $dates = Audio::select('date')->where('user_id', $check->id)->distinct('date')->orWhere('recipient_id', $check->id)->distinct('date')->pluck('date')->toArray();
                $times  = Audio::select('time')->where('user_id', $check->id)->whereIn('date', $dates)->distinct('time')->orWhere('recipient_id', $check->id)->whereIn('date', $dates)->distinct('time')->pluck('time');
                $audioData =  Audio::select('id', 'user_id', 'recipient_id', 'date', 'time', 'audio AS media', 'singer')->whereIn('date', $dates)->whereIn('time', $times)->latest()->get();
                foreach ($audioData as $item) {
                    $item->notes = [];
                    $item->title = '';
                    $item->description = '';
                    $item->name = '';
                    $item->image = '';
                    $item->user = User::find($item->user_id);
                    $item->recipient = User::find($item->recipient_id);
                }
                $aData1 = new stdClass();
                $aData1->type = 'audio';
                $aData1->audio = $audioData;
                $main[] = $aData1;
                $dates = Video::select('date')->where('user_id', $check->id)->distinct('date')->orWhere('recipient_id', $check->id)->distinct('date')->pluck('date')->toArray();
                $times  = Video::select('time')->where('user_id', $check->id)->whereIn('date', $dates)->distinct('time')->orWhere('recipient_id', $check->id)->whereIn('date', $dates)->distinct('time')->pluck('time');
                $videoData =  Video::select('id', 'user_id', 'recipient_id', 'date', 'time', 'video AS media')->whereIn('date', $dates)->whereIn('time', $times)->latest()->get();
                foreach ($videoData as $item) {
                    $item->notes = [];
                    $item->title = '';
                    $item->description = '';
                    $item->name = '';
                    $item->image = '';
                    $item->singer = '';
                    $item->user = User::find($item->user_id);
                    $item->recipient = User::find($item->recipient_id);
                }
                $aData2 = new stdClass();
                $aData2->type = 'video';
                $aData2->video = $videoData;
                $main[] = $aData2;
                $dates = Voice::select('date')->where('user_id', $check->id)->distinct('date')->orWhere('recipient_id', $check->id)->distinct('date')->pluck('date')->toArray();
                $times  = Voice::select('time')->where('user_id', $check->id)->whereIn('date', $dates)->distinct('time')->orWhere('recipient_id', $check->id)->whereIn('date', $dates)->distinct('time')->pluck('time');
                $voiceData =  Voice::select('id', 'user_id', 'recipient_id', 'date', 'time', 'voice AS media')->whereIn('date', $dates)->whereIn('time', $times)->latest()->get();
                foreach ($voiceData as $item) {
                    $item->notes = [];
                    $item->title = '';
                    $item->description = '';
                    $item->name = '';
                    $item->image = '';
                    $item->singer = '';
                    $item->user = User::find($item->user_id);
                    $item->recipient = User::find($item->recipient_id);
                }
                $aData3 = new stdClass();
                $aData3->type = 'voice';
                $aData3->voice = $voiceData;
                $main[] = $aData3;
                $notes = Note::select('user_id', 'recipient_id', 'date', 'time')->where('user_id', $check->id)->distinct()->get();
                foreach ($notes as $note) {
                    $note->notes = Note::select('id', 'title', 'description')->where('user_id', $check->id)->where('recipient_id', $note->recipient_id)->whereDate('date', $note->date)->where('time', $note->time)->distinct()->get();
                    $note->media = [];
                    $note->id = 0;
                    $note->title = '';
                    $note->description = '';
                    $note->name = '';
                    $note->image = '';
                    $note->singer = '';
                    $note->recipient = User::find($note->recipient_id);
                    $note->user = User::find($note->user_id);
                }
                $aData4 = new stdClass();
                $aData4->type = 'note';
                $aData4->note = $notes;
                $main[] = $aData4;
                $reminders = Reminder::select('id', 'user_id', 'remind_date AS date', 'remind_time AS time', 'title', 'description')->where('user_id', $check->id)->latest()->get();
                foreach ($reminders as $rem) {
                    $rem->media = [];
                    $rem->recipient_id = 0;
                    $rem->notes = [];
                    $rem->name = '';
                    $rem->singer = '';
                    $rem->image = '';
                    $rem->user = User::find($rem->user_id);
                    $rem->recipient = new stdClass();
                }
                $aData5 = new stdClass();
                $aData5->type = 'reminder';
                $aData5->reminder = $reminders;
                $main[] = $aData5;
                $passwords = Password::select('id', 'title', 'password AS description')->where('user_id', $check->id)->latest()->get();
                foreach ($passwords as $pass) {
                    $pass->date = '';
                    $pass->time = '';
                    $pass->media = [];
                    $pass->notes = [];
                    $pass->name = '';
                    $pass->image = '';
                    $pass->singer = '';
                    $pass->user_id = 0;
                    $pass->recipient_id = 0;
                    $pass->user = new stdClass();
                    $pass->recipient = new stdClass();
                }
                $aData6 = new stdClass();
                $aData6->type = 'password';
                $aData6->password = $passwords;
                $main[] = $aData6;
                $friends = Friend::where('user_id', $check->id)->where('accepted', 1)->pluck('friend_id');
                $friends1 = Friend::where('friend_id', $check->id)->where('accepted', 1)->pluck('user_id');
                $ids = $friends->merge($friends1);
                $friends = User::whereIn('id', $ids)->get();
                foreach ($friends as $friend) {
                    $friend->date = '';
                    $friend->time = '';
                    $friend->media = [];
                    $friend->notes = [];
                    $friend->title = '';
                    $friend->singer = '';
                    $friend->description = '';
                    $friend->user_id = 0;
                    $friend->recipient_id = 0;
                    $friend->user = new stdClass();
                    $friend->recipient = new stdClass();
                }
                $aData7 = new stdClass();
                $aData7->type = 'friend';
                $aData7->friend = $friends;
                $main[] = $aData7;
                return response(['status' => true, 'data' => $main, 'action' => 'Legacy code']);
            } else
                return response(['status' => false, 'data' => array(), 'action' => 'Legacy code not found']);
        } else
            return response(['status' => false, 'data' => array(), 'action' => 'User not found']);
    }

    public function updateToken($id, $device, $deviceName, $token)
    {
        $update = UserDevice::where('user_id', $id)->where('device_id', $device)->where('device', $deviceName)->first();
        if ($update) {
            $update->fcm_token = $token;
            $update->save();
        } else {
            $device1 = new UserDevice();
            $device1->user_id = $id;
            $device1->device_id = $device;
            $device1->device = $deviceName;
            $device1->fcm_token = $token;
            $device1->save();
        }
    }

    public function pushnotification($tokens, $title, $body, $arr, $badge = 0)
    {
        $fcmUrl = 'https://fcm.googleapis.com/fcm/send';

        $notification = [
            'title' => $title,
            'body' => $body,
            'sound' => true,
            'badge' => $badge,
        ];

        $extraNotificationData = $arr;

        $fcmNotification = [
            'registration_ids' => $tokens, //single token
            'notification' => $notification,
            'data' => $extraNotificationData
        ];

        $headers = [
            'Authorization: key=AAAAHVlvrME:APA91bG0NWIelHGhpXq7inhIRoV70ulXoY8H06opPTUwF0gN_bsnVslv3PJvIWXMkL16UrsqexsBOugR-qqtY2F7m1qNqbicK0hPR5pFgAeB7S5zDXPKC9wqTxj_OSXaSKqU5wmC5bhZ',
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fcmUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmNotification));
        $result = curl_exec($ch);
        curl_close($ch);
    }
}
