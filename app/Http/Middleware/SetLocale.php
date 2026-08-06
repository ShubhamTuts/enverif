<?php
namespace App\Http\Middleware;use Closure;use Illuminate\Http\Request;use Symfony\Component\HttpFoundation\Response;
final class SetLocale {public function handle(Request $request,Closure $next):Response{$allowed=array_keys(config('enverif.locales',[]));$locale=$request->user()?->locale?:session('locale',config('app.locale'));if(!in_array($locale,$allowed,true))$locale=config('app.fallback_locale','en');app()->setLocale($locale);return $next($request);}}
