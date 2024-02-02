<?php
/** Files Utility v3.0.1 */
namespace App\Controllers\Utils;
use CodeIgniter\Files\File;

class Files {
    const DEFAULT_PATH = WRITEPATH . 'uploads/';

    protected $file;
    protected string $path;
    protected int $maxSize;
    protected string $fileName;
    protected array $allowedExtensions;


    /**
     * Upload file. It complements with the protected params.
     * @param File $file The file in raw.
     * @return string
     */
    public function upload($file): string
    {
        if(!empty($file)){
            $this->file = $file;

            if(!$this->checkExtension()) return 'Extension not allowed.';
            if(!$this->checkMaxSize()) return 'The file exceeds the maximum size.';
            if(!$this->checkFileName()) return 'File name have wrong characters.';

            return $this->saveFile();

        }else return 'Empty file';
    }

    /**
     * List of the path
     * @param string|null $path the path you want to list. If empty will list the default path.
     * @return string|array
     */
    public function list(string $path = self::DEFAULT_PATH){
        if(is_dir($path)){
            $list = scandir($path);

            return array_diff($list, ['.', '..']);

        }else return 'Directory not found';
    }

    /**
     * Download one file.
     * @param string $fileName file name.
     * @param string|null $path the path of the file, if null will be search default path.
     *
     */
    public function download(string $fileName, string $path = null){
        if(!isset($path)) $path = self::DEFAULT_PATH;

        if(is_file($path . $fileName)) {
            $download = new File($path . $fileName);

            $streamer = new Streamer($download);
            $streamer->start();

        }else return 'File not found.';
    }

    /**
     * Remove Files.
     * If you send the name and not the path will be removed the file in the default path.
     * If you send only the path, will be removed all files and directories of the path.
     * If you send both params will be removed the file of the path you want.
     * @param string|null $fileName File name to remove.
     * @param string|null $path Path if it's not the default path (end it with "/").
     * @param bool|false $removeIfEmpty If the directory is empty will be removed.
     * @return string in fail or bool
     */
    public function remove(string $fileName = NULL, string $path = NULL, bool $removeIfEmpty = false){
        if (isset($fileName) || isset($path)) {
            if (!isset($path)) $path = self::DEFAULT_PATH;

            if (isset($fileName)) {
                if (chmod($path, 0777) && file_exists($path . $fileName)) $response = unlink($path . $fileName);
                else $response = 'File does not exist';
            } else {
                if (file_exists($path)) {
                    $files = glob($path . '/*');

                    foreach ($files as $file) {
                        if (is_file($file)) {
                            if (!unlink($file)) return 'File ' . $file . ' can not be removed';

                        } else if (is_dir($file)) {
                            if (!$this->deleteDirectory($file)) return 'Directory ' . $file . ' can not be removed';
                        }
                        $response = true;
                    }
                }
            }

            if ($removeIfEmpty && isset($path) && file_exists($path)) {
                $filesPath = glob($path . '/*');
                if (count($filesPath) === 0) rmdir($path);
            }

        } else {
            $response = 'Nothing to remove.';
        }

        return $response ?? 'No response';
    }

    /**
     * Duplicate a file or a directory.
     * @param array $parameters {
     * "actual-path" The actual path.
     * "actual-file-name" The name of the file (Optional).
     * "destination-path" The destination path, if doesn't exist will be created.
     * "destination-file-name" The new name of the copy (Optional).
     * }
     * @return string|bool string in fail.
     */
    public function duplicate(array $parameters){
        if(empty($parameters)) return 'The parameters for duplicate are empty.';
        if(!isset($parameters['actual-path']) || !isset($parameters['destination-path'])) return 'The actual path and destination path are require.';

        if(isset($parameters['actual-file-name'])){ // Single file
            $actualCompletePath = $parameters['actual-path'] . $parameters['actual-file-name'];

            if(file_exists($actualCompletePath)){
                if(!is_dir($parameters['destination-path'])){
                    if(!mkdir($parameters['destination-path'])) return 'Error creating the new directory.';
                }

                $destinationCompletePath = $parameters['destination-path'] . (!empty($parameters['destination-file-name']) ? $parameters['destination-file-name'] : $parameters['actual-file-name']);
                return copy($actualCompletePath, $destinationCompletePath);

            }else return 'File not found "' . $parameters['actual-file-name'] . '"';

        }else { // Complete directory
            if(is_dir($parameters['actual-path'])){
                $this->recursiveCopy($parameters['actual-path'], $parameters['destination-path']);
                return true;

            }else return 'actual-path it\'s not a directory';
        }
    }

    /**
     * Save file. This is not necessary if you use upload().
     * You can use this if you need more control.
     *
     * */
    public function saveFile(): string
    {
        if(!isset($this->path)) $this->path = self::DEFAULT_PATH;

        //If the dir don't exist, create it.
        if(!is_dir($this->path)) mkdir($this->path, 0777, true);
        if(file_exists($this->path . $this->fileName)) return 'File already exist';

        $response = $this->file->move($this->path, $this->fileName);
        return $response ? 'SUCCESS' : 'Error trying to save the file.';
    }


    /**
     * Set file. If you are using upload(), this have to be ignored it.
     * This is only for use the saveFile().
     * @param File $file the raw file.
     */
    public function setFile($file){
        $this->file = $file;
    }
    /**
     * Set file name. If you are using upload(), this have to be ignored it.
     * This is only for use the saveFile().
     * @param string $fileName the name has to include the extension.
     */
    public function setFileName(string $fileName){
        $this->fileName = $fileName;
    }
    /**
     * Set path if you don't need default path.
     * @param string $path
     * */
    public function setPath(string $path){
        $this->path = $path;
    }
    /**
     * Set allowed extensions. If this is not set there's no filter.
     * @param array $extensions
     * */
    public function setAllowedExtensions(array $extensions){
        $this->allowedExtensions = $extensions;
    }
    /**
     * Set maximum file size.
     * @param int $maxSize in bytes.
     * */
    public function setMaxSize(int $maxSize){
        $this->maxSize = $maxSize;
    }


    private function checkExtension(): bool
    {
        if (isset($this->allowedExtensions)) return in_array($this->file->getClientExtension(), $this->allowedExtensions);
        else return true;
    }
    private function checkMaxSize(): bool
    {
        if(isset($this->maxSize)) return $this->file->getSize() <= $this->maxSize;
        else return true;
    }
    private function checkFileName(): bool
    {
        if(!isset($this->fileName)) $this->fileName = $this->file->getName();

        if(preg_match('/^[a-zA-Z0-9_.-]+$/', $this->fileName) || strlen($this->fileName) <= 250) return true;
        else return false;
    }

    private function deleteDirectory($dir): bool
    {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);

        $scan = scandir($dir);
        foreach ($scan as $item) {
            if ($item == '.' || $item == '..') continue;
            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
        }
        return rmdir($dir);
    }

    private function recursiveCopy($src, $dst) {
        $dir = opendir($src);
        if(!file_exists($dst)) @mkdir($dst);

        while(false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) $this->recursiveCopy($src . '/' . $file, $dst . '/' . $file);
                else copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
        closedir($dir);
    }

}
