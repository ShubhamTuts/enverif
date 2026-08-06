<?php

namespace App\Http\Controllers;

use App\Models\ConnectorConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class ConnectorOAuthController extends Controller
{
    public function start(Request $request, ConnectorConnection $connector)
    {
        abort_unless(in_array($connector->driver, ['gmail','outlook'], true), 404);
        $clientId=(string)data_get($connector->configuration,'client_id','');
        try {
            $secret=$connector->credential('client_secret');
        } catch (\Throwable $e) {
            if (\App\Support\EncryptedCredentials::isDecryptFailure($e)) {
                return redirect()->route('connectors.edit', $connector)->with('error', \App\Support\EncryptedCredentials::CONNECTOR_DECRYPT_MESSAGE);
            }
            throw $e;
        }
        abort_if($clientId===''||!$secret,422,'Save the OAuth client ID and client secret first.');
        $state=Str::random(64); session(['connector_oauth_'.$state=>['connector_id'=>$connector->id,'driver'=>$connector->driver,'expires'=>time()+600]]);
        $callback=route('connectors.oauth.callback',['driver'=>$connector->driver]);
        if($connector->driver==='gmail'){
            $query=http_build_query(['client_id'=>$clientId,'redirect_uri'=>$callback,'response_type'=>'code','scope'=>'openid email https://www.googleapis.com/auth/gmail.modify','access_type'=>'offline','prompt'=>'consent','state'=>$state]);
            return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
        }
        $tenant=(string)data_get($connector->configuration,'tenant','common');
        $query=http_build_query(['client_id'=>$clientId,'redirect_uri'=>$callback,'response_type'=>'code','response_mode'=>'query','scope'=>'offline_access User.Read Mail.ReadWrite Mail.Send','state'=>$state]);
        return redirect()->away('https://login.microsoftonline.com/'.rawurlencode($tenant).'/oauth2/v2.0/authorize?'.$query);
    }

    public function callback(Request $request,string $driver)
    {
        abort_unless(in_array($driver,['gmail','outlook'],true),404);
        if($request->filled('error'))return redirect()->route('connectors.index')->with('error','Mailbox authorization was declined: '.(string)$request->input('error_description',$request->input('error')));
        $state=(string)$request->query('state',''); $stored=session()->pull('connector_oauth_'.$state); abort_unless(is_array($stored)&&($stored['driver']??null)===$driver&&(int)($stored['expires']??0)>=time(),403);
        $connector=ConnectorConnection::findOrFail((int)$stored['connector_id']);
        $clientId=(string)data_get($connector->configuration,'client_id',''); $secret=(string)$connector->credential('client_secret'); $callback=route('connectors.oauth.callback',['driver'=>$driver]); $code=(string)$request->query('code',''); abort_if($code==='',422,'Authorization code missing.');
        if($driver==='gmail'){
            $response=Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token',['client_id'=>$clientId,'client_secret'=>$secret,'code'=>$code,'grant_type'=>'authorization_code','redirect_uri'=>$callback]);
        }else{
            $tenant=(string)data_get($connector->configuration,'tenant','common');
            $response=Http::asForm()->timeout(30)->post('https://login.microsoftonline.com/'.rawurlencode($tenant).'/oauth2/v2.0/token',['client_id'=>$clientId,'client_secret'=>$secret,'code'=>$code,'grant_type'=>'authorization_code','redirect_uri'=>$callback,'scope'=>'offline_access User.Read Mail.ReadWrite Mail.Send']);
        }
        $payload=$response->throw()->json();
        try {
            $credentials = $connector->decryptedCredentials();
        } catch (\Throwable $e) {
            if (! \App\Support\EncryptedCredentials::isDecryptFailure($e)) {
                throw $e;
            }
            $credentials = [];
        }
        $credentials['access_token']=(string)($payload['access_token']??''); if(!empty($payload['refresh_token']))$credentials['refresh_token']=(string)$payload['refresh_token']; $credentials['expires_at']=time()+max(60,(int)($payload['expires_in']??3600)-60); $connector->update(['credentials'=>$credentials,'last_test_status'=>'ok','last_tested_at'=>now()]);
        return redirect()->route('connectors.edit',$connector)->with('status','Mailbox connected successfully.');
    }

    public function disconnect(ConnectorConnection $connector)
    {
        abort_unless(in_array($connector->driver,['gmail','outlook'],true),404);
        try {
            $credentials = $connector->decryptedCredentials();
        } catch (\Throwable $e) {
            if (! \App\Support\EncryptedCredentials::isDecryptFailure($e)) {
                throw $e;
            }
            $credentials = [];
        }
        unset($credentials['access_token'],$credentials['refresh_token'],$credentials['expires_at']); $connector->update(['credentials'=>$credentials,'last_test_status'=>'disconnected']); return back()->with('status','Mailbox disconnected.');
    }
}
