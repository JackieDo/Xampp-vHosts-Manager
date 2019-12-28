<?php

class Console
{
    protected static $simpleConsole;
    protected static $hline = '-----------------------------------------------------------------------------------';

    public static function setDefaultMessages($defaultMessages = array())
    {
        self::getSimpleConsole()->setDefaultMessages($defaultMessages);
    }

    public static function confirm($question, $default = true)
    {
        return self::getSimpleConsole()->askConfirm($question, $default);
    }

    public static function ask($message, $defaultValue = null)
    {
        return self::getSimpleConsole()->askInput($message, $defaultValue);
    }

    public static function line($message = null, $breakLine = true, $beginAtColumn = 0)
    {
        self::getSimpleConsole()->showMessage($message, $breakLine, $beginAtColumn);
    }

    public static function breakline($multiplier = 1)
    {
        self::getSimpleConsole()->showMessage(str_repeat(PHP_EOL, $multiplier), false);
    }

    public static function hrline($width = 83, $symbol = '-')
    {
        self::getSimpleConsole()->showMessage(str_repeat($symbol, $width));
    }

    public static function terminate($message = null, $exitStatus = 0)
    {
        self::getSimpleConsole()->terminate($message, $exitStatus);
    }

    private static function getSimpleConsole()
    {
        if (is_null(self::$simpleConsole)) {
            self::$simpleConsole = new SimpleConsole;
        }

        return self::$simpleConsole;
    }
}

class SimpleConsole
{
    protected $defaultMessages = array(
        'terminate' => 'Program is terminating...'
    );

    public function __construct($defaultMessages = array())
    {
        $this->setDefaultMessages($defaultMessages);
    }

    public function setDefaultMessages($defaultMessages = array())
    {
        if (is_array($defaultMessages)) {
            foreach ($this->defaultMessages as $key => $value) {
                if (array_key_exists($key, $defaultMessages) && !empty($defaultMessages[$key])) {
                    $this->defaultMessages[$key] = $defaultMessages[$key];
                }
            }
        }
    }

    public function askConfirm($question, $default = true, $trueChar = 'y', $falseChar = 'n')
    {
        $yes = strtolower($trueChar);
        $no  = strtolower($falseChar);

        if ($default) {
            $yes = '"' . $trueChar . '"';
        } else {
            $no = '"' . $falseChar . '"';
        }

        $this->showMessage($question . ' [' . $yes . '|' . $no . ']: ', false);
        $answer = $this->getInputFromKeyboard((($default) ? $trueChar : $falseChar));

        return strtolower($answer) == $trueChar;
    }

    public function askInput($message, $defaultValue = null)
    {
        $this->showMessage($message . ((! is_null($defaultValue)) ? ' ["' . $defaultValue . '"]' : null) . ': ', false);
        $answer = $this->getInputFromKeyboard(((! is_null($defaultValue)) ? $defaultValue : null));

        return $answer;
    }

    public function showMessage($message = null, $breakLine = true, $beginAtColumn = 0)
    {
        $spaceBefore = ($beginAtColumn > 0) ? str_repeat(' ', $beginAtColumn) : '';

        echo $spaceBefore . $message;

        if ($breakLine) {
            echo PHP_EOL;
        }
    }

    public function terminate($message = null, $exitStatus = 0)
    {
        if (! is_null($message)) {
            $message .= PHP_EOL;
        }

        $this->showMessage($message . $this->defaultMessages['terminate']);
        exit($exitStatus);
    }

    private function getInputFromKeyboard($defaultValue = null)
    {
        $userInput = $defaultValue;
        $hStdin    = fopen('php://stdin', 'r');
        $userInput = (trim(fgets($hStdin, 256)));
        $userInput = (!$userInput) ? $defaultValue : $userInput;

        fclose($hStdin);

        return $userInput;
    }
}
