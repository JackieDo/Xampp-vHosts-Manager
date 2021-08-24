<?php

namespace VhostsManager\Support;

class Setting
{
    protected $storage;
    protected $settings = [];

    public function __construct($storage = null)
    {
        if ($storage) {
            $this->setStorage($storage);
        }

        $this->reload();
    }

    public function setStorage($filename)
    {
        $this->storage = $filename;

        return $this;
    }

    public function storageExists()
    {
        return is_file($this->storage);
    }

    public function reload()
    {
        if (is_file($this->storage)) {
            $this->settings = @parse_ini_file($this->storage, true);
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
            $this->settings[$section] = [];
        }

        $this->settings[$section][$setting] = $value;

        return $this;
    }

    public function save()
    {
        return @create_ini_file($this->storage, $this->settings, true);
    }
}
