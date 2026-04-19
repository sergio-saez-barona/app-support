<?php


namespace AppSupport\Console;

use AppSupport\Support\Facades\Log;

class InputFile extends Argument
{
    /**
     * @param string $argumentStr
     * @return bool
     */
    public function parse(string $argumentStr): bool
    {
        if (!parent::parse($argumentStr)) {
            return false;
        }

        if (!file_exists($argumentStr) || !is_readable($argumentStr)) {
            Log::error("Input file '$argumentStr' cannot be read");
            return false;
        }

        return true;
    }

}