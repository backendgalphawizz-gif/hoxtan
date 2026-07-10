<?php

return [

  'length' => (int) env('OTP_LENGTH', 4),

  'expires_in_seconds' => (int) env('OTP_EXPIRES_IN', 300),

  'resend_after_seconds' => (int) env('OTP_RESEND_AFTER', 30),

  'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

  'registration_session_ttl' => (int) env('REGISTRATION_SESSION_TTL', 1800),

  /*
  |--------------------------------------------------------------------------
  | Expose OTP in API response (local / QA only)
  |--------------------------------------------------------------------------
  */
  'expose_in_response' => env('OTP_EXPOSE_IN_RESPONSE', env('APP_DEBUG', false)),

  'mpin_length' => (int) env('MPIN_LENGTH', 4),

];
