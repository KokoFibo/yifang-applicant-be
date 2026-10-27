<?php

namespace App\Support;

class DomainName
{
    /** "https://Sti-Applicant.Yifang.co.id:443/x" -> "sti-applicant.yifang.co.id" (null jika tidak valid) */
    public static function normalize(string $input): ?string
    {
        $d = strtolower(trim($input));
        $d = preg_replace('#^[a-z]+://#', '', $d);
        $d = preg_replace('#[/:?].*$#', '', $d);
        $d = rtrim($d, '.');

        $ok = preg_match('/^(?=.{1,190}$)[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/', $d);

        return $ok ? $d : null;
    }
}
