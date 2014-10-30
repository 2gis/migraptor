<?php

/**
 * Class CLIHelper
 */
abstract class CLIHelper
{
    const COLOR_BLACK = '0;30';
    const COLOR_DARK_GRAY = '1;30';
    const COLOR_BLUE = '0;34';
    const COLOR_LIGHT_BLUE = '1;34';
    const COLOR_GREEN = '0;32';
    const COLOR_LIGHT_GREEN = '1;32';
    const COLOR_CYAN = '0;36';
    const COLOR_LIGHT_CYAN = '1;36';
    const COLOR_RED = '0;31';
    const COLOR_LIGHT_RED = '1;31';
    const COLOR_PURPLE = '0;35';
    const COLOR_LIGHT_PURPLE = '1;35';
    const COLOR_BROWN = '0;33';
    const COLOR_YELLOW = '1;33';
    const COLOR_LIGHT_GRAY = '0;37';
    const COLOR_WHITE = '1;37';

    const BGCOLOR_black = '40';
    const BGCOLOR_red = '41';
    const BGCOLOR_green = '42';
    const BGCOLOR_yellow = '43';
    const BGCOLOR_blue = '44';
    const BGCOLOR_magenta = '45';
    const BGCOLOR_cyan = '46';
    const BGCOLOR_light_gray = '47';

    const COLOR_INFO = self::COLOR_BLUE;
    const COLOR_WARNING = self::COLOR_BROWN;
    const COLOR_ERROR = self::COLOR_RED;
    const COLOR_FATAL = self::COLOR_LIGHT_RED;
    const COLOR_SUCCESS = self::COLOR_GREEN;
    const COLOR_FAIL = self::COLOR_RED;

    const EOL = PHP_EOL;
    const TAB = "\t";

    public static function output($out)
    {
        echo $out;
        return flush();
    }

    public static function outputLn($out)
    {
        return self::output($out.self::EOL);
    }

    /**
     * @param $string
     * @param string $foregroundColor
     * @param string $backgroundColor
     * @return string
     */
    public static function writeColored($string, $foregroundColor = null, $backgroundColor = null)
    {
        $res = "";

        if ($foregroundColor) {
            $res .= "\033[" . $foregroundColor . "m";
        }

        if ($backgroundColor) {
            $res .= "\033[" . $backgroundColor . "m";
        }

        $res .= $string . "\033[0m";

        return $res;
    }

    /**
     * @param $string
     * @param string $foregroundColor
     * @param string $backgroundColor
     * @return string
     */
    public static function writeColoredLn($string, $foregroundColor = null, $backgroundColor = null)
    {
        return self::writeColored($string.self::EOL, $foregroundColor, $backgroundColor);
    }

    /**
     * @param $string
     * @return string
     */
    public static function writeInfo($string)
    {
        return self::writeColored($string, self::COLOR_INFO);
    }

    /**
     * @param $string
     * @return string
     */
    public static function writeInfoLn($string)
    {
        return self::writeInfo($string.self::EOL);
    }

    /**
     * @param $string
     * @return string
     */
    public static function writeWarning($string)
    {
        return self::writeColored($string, self::COLOR_WARNING);
    }

    /**
     * @param $string
     * @return string
     */
    public static function writeWarningLn($string)
    {
        return self::writeWarning($string.self::EOL);
    }

    /**
     * @param $string
     * @return string
     */
    public static function writeError($string)
    {
        return self::writeColored($string, self::COLOR_ERROR);
    }

    /**
     * @param $string
     * @return string
     */
    public static function writeErrorLn($string)
    {
        return self::writeError($string.self::EOL);
    }

    /**
     * @param $string
     * @return string
     */
    public static function writeFatal($string)
    {
        return self::writeColored($string, self::COLOR_FATAL);
    }

    /**
     * @param $string
     * @return string
     */
    public static function writeFatalLn($string)
    {
        return self::writeFatal($string.self::EOL);
    }

    /**
     * @param $string
     * @return string
     */
    public static function writeSuccess($string)
    {
        return self::writeColored($string, self::COLOR_SUCCESS);
    }

    /**
     * @param $string
     * @return string
     */
    public static function writeSuccessLn($string)
    {
        return self::writeSuccess($string.self::EOL);
    }

    /**
     * @param $string
     * @return string
     */
    public static function writeFail($string)
    {
        return self::writeColored($string, self::COLOR_FAIL);
    }

    /**
     * @param $string
     * @return string
     */
    public static function writeFailLn($string)
    {
        return self::writeFail($string.self::EOL);
    }
}