<?php

namespace ChrisComposer\Tool;

use think\App;

class FileTool
{
    /**
     * 创建目录
     * @param $dir string 目录
     *
     * @return bool
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/21
     */
    public static function createFolders($dir)
    {
        return is_dir($dir) or (self::createFolders(dirname($dir)) and mkdir($dir, 0755));
    }

    /**
     * 格式化文件大小
     * @param $size
     *
     * @return string
     * @author Chris Yu <chrisyu@crabapple.top> 2023/5/23
     */
    public static function formatSize($size) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $i = 0;
        while ($size >= 1024 && $i < 4) {
            $size /= 1024;
            $i++;
        }
        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * 格式化文件大小
     * @param $size
     *
     * @return string
     * @author Chris Yu <chrisyu@crabapple.top> 2023/5/23
     */
    public static function formatSizeFromKb($size) {
        $units = array('KB', 'MB', 'GB', 'TB');
        $i = 0;
        while ($size >= 1024 && $i < 4) {
            $size /= 1024;
            $i++;
        }
        return round($size, 2) . ' ' . $units[$i];
    }

    public static function getMimeType($file)
    {
        // 使用 mime_content_type() 函数获取文件的 MIME 类型
        return mime_content_type($file);
    }

    /**
     * 获取子目录列表
     *
     * @param $dir
     *
     * @return array
     * @author Chris Yu <chrisyu@crabapple.top> 2023/10/13
     */
    public static function getSubDirListByDir($dir)
    {
        $dir = rtrim($dir, '/');

        $output = [];
        if (!is_dir($dir)) {
            return $output;
        }
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $output[] = $dir . '/' . $file;
        }
        return $output;
    }

    /**
     * 统计目录大小
     * @param $dir
     *
     * @return false|int
     * @author Chris Yu <chrisyu@crabapple.top> 2023/5/29
     */
    public static function getDirectorySize($dir)
    {
        $size = 0;
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            if (is_file($path)) {
                $size += filesize($path);
            }
            else if (is_dir($path)) {
                $size += self::getDirectorySize($path);
            }
        }
        return $size;
    }

    /**
     * 获取在指定目录中，以某关键词为前缀的文件的数量
     * @param $dir string 目录
     * @param $prefix string 关键词
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/28
     */
    public static function countInDirByKeywords($dir, $prefix)
    {
        $files = scandir($dir);

        // 计数器
        $count = 0;

        // 遍历文件列表
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            // 检查文件名是否以指定前缀开头
            if (strpos($file, $prefix) === 0) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 获取最新创建的文件
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/29
     */
    public static function getLastCreateTimeFilePath($absoluteFilePathList)
    {
        $lastCreateTime = null;
        $lastCreatedFilepath = '';
        foreach ($absoluteFilePathList as $file) {
            $filectime = filectime($file);
            if (empty($lastCreateTime) || $filectime > $lastCreateTime) {
                $lastCreateTime = $filectime;
                $lastCreatedFilepath = $file;
            }
        }
        return $lastCreatedFilepath;
    }

    /**
     * 查询子目录根据关键词(不轮循)
     * @param $keywords
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/8/24
     */
    public static function getSubDirListByKeywords($dir, $keywords, $isAbsolutePath = true)
    {
        $subDirList = [];
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..') {
                $path = $dir . '/' . $item;

                if (is_dir($path)) {
                    if (strpos($item, $keywords) !== false) {
                        $subDirList[] = $isAbsolutePath ? $path : $item;
                    }
                }
            }
        }

        return $subDirList;
    }

    /**
     * 根据选项删除指定目录的文件和子目录
     * @param $dir string 目录
     * @param $options array 选项
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/8/24
     */
    public static function deleteFilesAndSubDirByOptions($dir, $options)
    {
        $startTime = $options['startTime'] ?? null;
        $endTime = $options['endTime'] ?? null;
        $files = glob($dir . '/*');
        $deleteNum = 0;
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    $filemtime = filemtime($file);
                    // 如果文件在指定时间段内，则删除
                    $isOk = false;
                    if ($startTime && $endTime) {
                        if ($filemtime > $startTime && $filemtime < $endTime) {
                            $isOk = true;
                        }
                    }
                    elseif ($startTime) {
                        if ($filemtime > $startTime) {
                            $isOk = true;
                        }
                    }
                    elseif ($endTime) {
                        if ($filemtime < $endTime) {
                            $isOk = true;
                        }
                    }

                    if ($isOk) {
                        unlink($file);
                        $deleteNum++;
                    }
                }
                elseif (is_dir($file)) {
                    // 递归删除子目录
                    $deleteNum += self::deleteFilesAndSubDirByOptions($file, $options);
                    // 如果子目录为空，则删除子目录
                    if (count(glob($file . '/*')) === 0) {
                        @rmdir($file);
                    }
                }
            }
        }
        return $deleteNum;
    }
}
