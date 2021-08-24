<?php

namespace VhostsManager\Support;

class Console
{
    protected static $defaultMessages = [
        'terminate' => 'Program is terminating...'
    ];

    public static function setDefaultMessages($defaultMessages = [])
    {
        if (is_array($defaultMessages)) {
            foreach (self::$defaultMessages as $key => $value) {
                if (array_key_exists($key, $defaultMessages) && !empty($defaultMessages[$key])) {
                    self::$defaultMessages[$key] = $defaultMessages[$key];
                }
            }
        }
    }

    public static function line($message = null, $breakLine = true, $beginAtColumn = 0)
    {
        $spaceBefore = ($beginAtColumn > 0) ? str_repeat(' ', $beginAtColumn) : '';

        echo $spaceBefore . $message;

        if ($breakLine) {
            echo PHP_EOL;
        }
    }

    public static function breakline($multiplier = 1)
    {
        self::line(str_repeat(PHP_EOL, $multiplier), false);
    }

    public static function hrline($width = 83, $symbol = '-')
    {
        self::line(str_repeat($symbol, $width));
    }

    public static function terminate($message = null, $exitStatus = 0, $silentMode = false)
    {
        if (! $silentMode) {
            if (! is_null($message)) {
                $message .= PHP_EOL;
            }

            self::line($message . self::$defaultMessages['terminate']);
        }

        exit($exitStatus);
    }

    public static function ask($message, $defaultValue = null)
    {
        self::line($message . ((! is_null($defaultValue)) ? ' ["' . $defaultValue . '"]' : null) . ': ', false);

        $answer = self::getInputFromKeyboard(((! is_null($defaultValue)) ? $defaultValue : null));

        return $answer;
    }

    public static function confirm($question, $default = true, $trueChar = 'y', $falseChar = 'n')
    {
        $yes = strtolower($trueChar);
        $no  = strtolower($falseChar);

        if ($default) {
            $yes = '"' . $trueChar . '"';
        } else {
            $no = '"' . $falseChar . '"';
        }

        self::line($question . ' [' . $yes . '|' . $no . ']: ', false);

        $answer = self::getInputFromKeyboard((($default) ? $trueChar : $falseChar));

        return strtolower($answer) == $trueChar;
    }

    private static function getInputFromKeyboard($defaultValue = null)
    {
        $userInput = $defaultValue;
        $hStdin    = fopen('php://stdin', 'r');
        $userInput = (trim(fgets($hStdin, 256)));
        $userInput = (is_null($userInput) || $userInput === '') ? $defaultValue : $userInput;

        fclose($hStdin);

        return $userInput;
    }
}
