<?php

class Setting
{
    protected $settings = array();

    public function __construct()
    {
        $this->reloadSettings();
    }

    public function reloadSettings()
    {
        if (is_file(getenv('XVHM_APP_DIR') . '\settings.ini')) {
            $this->settings = @parse_ini_file(getenv('XVHM_APP_DIR') . '\settings.ini', true);
        }

        return $this;
    }

    public function all()
    {
        return $this->settings;
    }

    public function get($section, $setting, $defaultValue = null)
    {
        if (array_key_exists($section, $this->settings)) {
            $returnValue = (array_key_exists($setting, $this->settings[$section])) ? $this->settings[$section][$setting] : $defaultValue;
        } else {
            $returnValue = $defaultValue;
        }

        return $returnValue;
    }

    public function set($section, $setting, $value)
    {
        if (! array_key_exists($section, $this->settings)) {
            $this->settings[$section] = array();
        }

        $this->settings[$section][$setting] = $value;

        return $this;
    }

    public function save()
    {
        $settings = $this->settings;
        $content  = '';

        foreach ($settings as $section => $section_settings) {
            $content .= PHP_EOL . '[' . $section. ']' . PHP_EOL;

            foreach ($section_settings as $setting => $value) {
                if (is_array($value)) {
                    for ($i = 0; $i < count($value); $i++) {
                        $content .= $setting . '[] = "' . $value[$i] . '"' . PHP_EOL;
                    }
                } else if (empty($value)) {
                    $content .= $setting . ' = ' . PHP_EOL;
                } else {
                    $content .= $setting . ' = "' . $value . '"' . PHP_EOL;
                }
            }
        }

        return @file_put_contents(getenv('XVHM_APP_DIR') . '\settings.ini', ltrim($content));
    }
}
