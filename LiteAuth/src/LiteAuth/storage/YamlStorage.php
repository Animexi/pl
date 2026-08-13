<?php

namespace LiteAuth\storage;

class YamlStorage {
    
    private $dataFolder;
    private $cache = [];
    
    public function __construct($dataFolder) {
        $this->dataFolder = $dataFolder;
        @mkdir($this->dataFolder);
    }
    
    public function exists($name) {
        $file = $this->getFilePath($name);
        return file_exists($file);
    }
    
    public function get($name) {
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }
        
        $file = $this->getFilePath($name);
        if (!file_exists($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        $data = $this->parseYaml($content);
        
        $this->cache[$name] = $data;
        return $data;
    }
    
    public function save($name, $data) {
        $file = $this->getFilePath($name);
        $yaml = $this->dumpYaml($data);
        file_put_contents($file, $yaml);
        $this->cache[$name] = $data;
    }
    
    public function delete($name) {
        $file = $this->getFilePath($name);
        if (file_exists($file)) {
            unlink($file);
        }
        unset($this->cache[$name]);
    }
    
    private function getFilePath($name) {
        return $this->dataFolder . strtolower($name) . ".yml";
    }
    
    private function parseYaml($content) {
        $data = [];
        $lines = explode("\n", $content);
        $currentKey = null;
        $inArray = false;
        $arrayKey = null;
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            if (empty($trimmed) || strpos($trimmed, "#") === 0) {
                continue;
            }
            
            if (strpos($trimmed, ": ") !== false) {
                list($key, $value) = explode(": ", $trimmed, 2);
                $key = trim($key);
                $value = trim($value);
                
                if ($value === "") {
                    $currentKey = $key;
                    $data[$key] = [];
                    $inArray = true;
                    $arrayKey = $key;
                } else {
                    $data[$key] = $this->parseValue($value);
                    $inArray = false;
                }
            } elseif (strpos($trimmed, "- ") === 0 && $inArray && $arrayKey !== null) {
                $value = trim(substr($trimmed, 2));
                $data[$arrayKey][] = $this->parseValue($value);
            }
        }
        
        return $data;
    }
    
    private function parseValue($value) {
        if ($value === "true") {
            return true;
        }
        if ($value === "false") {
            return false;
        }
        if (is_numeric($value)) {
            return (int)$value;
        }
        if ($value === "null" || $value === "~") {
            return null;
        }
        if ((strpos($value, "\"") === 0 && strrpos($value, "\"") === strlen($value) - 1) ||
            (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
            return substr($value, 1, -1);
        }
        return $value;
    }
    
    private function dumpYaml($data, $indent = 0) {
        $yaml = "";
        $prefix = str_repeat("  ", $indent);
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (empty($value)) {
                    $yaml .= "$prefix$key: []\n";
                } elseif (array_keys($value) === range(0, count($value) - 1)) {
                    $yaml .= "$prefix$key:\n";
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $yaml .= "$prefix  -\n" . $this->dumpYaml($item, $indent + 2);
                        } else {
                            $yaml .= "$prefix  - " . $this->formatValue($item) . "\n";
                        }
                    }
                } else {
                    $yaml .= "$prefix$key:\n" . $this->dumpYaml($value, $indent + 1);
                }
            } else {
                $yaml .= "$prefix$key: " . $this->formatValue($value) . "\n";
            }
        }
        
        return $yaml;
    }
    
    private function formatValue($value) {
        if ($value === true) {
            return "true";
        }
        if ($value === false) {
            return "false";
        }
        if ($value === null) {
            return "null";
        }
        if (is_numeric($value)) {
            return $value;
        }
        if (strpos($value, ":") !== false || strpos($value, "#") !== false || 
            strpos($value, "[") !== false || strpos($value, "]") !== false ||
            strpos($value, "{") !== false || strpos($value, "}") !== false) {
            return "\"" . addslashes($value) . "\"";
        }
        return $value;
    }
}
