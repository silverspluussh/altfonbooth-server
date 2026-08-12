<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class V1NormalizeRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('post')) {
            $input = $request->all();

            // login: identifier may be posted as emailaddress instead of username
            if (empty($input['username']) && !empty($input['emailaddress'])) {
                $input['username'] = $input['emailaddress'];
            }

            // forgot-password / requestPasswordReset expects `email`
            if (empty($input['email']) && !empty($input['emailaddress'])) {
                $input['email'] = $input['emailaddress'];
            }

            // reset-password: map legacy field names
            if (empty($input['token']) && !empty($input['password_reset_token'])) {
                $input['token'] = $input['password_reset_token'];
            }
            if (empty($input['password']) && !empty($input['new_password'])) {
                $input['password'] = $input['new_password'];
            }

            // verifypayment: referenceid -> reference
            if (empty($input['reference']) && !empty($input['referenceid'])) {
                $input['reference'] = $input['referenceid'];
            }

            $request->merge($input);
        }

        return $next($request);
    }
}