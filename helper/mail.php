<?php

class mail
{
    public function exist($email)
    {
        list($userid, $domain) = explode('@', $email);
        if (checkdnsrr($domain, 'MX')) {
            return true;
        } else {
            return false;
        }
    }
}
