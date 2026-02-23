<?php
declare(strict_types=1);

namespace App\Helpers;

class TimeHelper
{
    /**
     * Devuelve una cadena legible con el tiempo transcurrido (Ej: Hace 5 minutos)
     */
    public static function timeAgo($timestamp): string
    {
        if (is_string($timestamp) && !is_numeric($timestamp)) {
            $timestamp = strtotime($timestamp);
        }

        $time = time() - $timestamp;

        if ($time < 1) return 'Justo ahora';

        $a = [
            365 * 24 * 60 * 60  =>  'año',
            30 * 24 * 60 * 60   =>  'mes',
            24 * 60 * 60        =>  'día',
            60 * 60             =>  'hora',
            60                  =>  'minuto',
            1                   =>  'segundo'
        ];
        
        $a_plural = [
            'año'    => 'años',
            'mes'    => 'meses',
            'día'    => 'días',
            'hora'   => 'horas',
            'minuto' => 'minutos',
            'segundo'=> 'segundos'
        ];

        foreach ($a as $secs => $str) {
            $d = $time / $secs;
            if ($d >= 1) {
                $r = round($d);
                return 'Hace ' . $r . ' ' . ($r > 1 ? $a_plural[$str] : $str);
            }
        }
        
        return 'Hace poco';
    }
}
