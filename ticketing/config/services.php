<?php
return [ 'sms'=>['driver'=>env('SMS_DRIVER','log'),'endpoint'=>env('SMS_ENDPOINT'),'fail'=>env('SMS_FAIL',false)], 'mail'=>['fail'=>env('MAIL_FAIL',false)], ];
