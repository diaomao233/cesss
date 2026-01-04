<?php
class JsonDB {
    private $dataDir;
    public function __construct($dataDir = 'data/') {
        $this->dataDir = $dataDir;
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }
    }
    public function get($file) {
        $path = $this->dataDir . $file;
        if (!file_exists($path)) return [];
        $content = file_get_contents($path);
        return json_decode($content, true) ?: [];
    }
    public function save($file, $data) {
        $path = $this->dataDir . $file;
        return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    public function insert($file, $record) {
        $data = $this->get($file);
        $data[] = $record;
        return $this->save($file, $data);
    }
    public function update($file, $field, $value, $newData) {
        $data = $this->get($file);
        foreach ($data as &$item) {
            if (isset($item[$field]) && $item[$field] == $value) {
                $item = array_merge($item, $newData);
                break;
            }
        }
        return $this->save($file, $data);
    }
    public function find($file, $field, $value) {
        $data = $this->get($file);
        foreach ($data as $item) {
            if (isset($item[$field]) && $item[$field] == $value) {
                return $item;
            }
        }
        return null;
    }
}
?>