<?php

namespace Vendimia\Str;

use BadFunctionCallException;
use ArrayAccess;
use Iterator;
use Stringable;

/**
 * String object implementation
 */
class Str implements ArrayAccess, Iterator, Stringable
{

    /** The actual string */
    private $string;

    /** Original string encoding */
    private $encoding;

    /** Iterator index */
    private $iter_index;

    /** Function mapping for simple PHP functions */
    private $functions = [
        'toUpper' => 'mb_strtoupper',
        'toLower' => 'mb_strtolower',
        'slice' => 'mb_substr',
        'length' => 'mb_strlen',
        'indexOf' => 'mb_strpos',
        'pad' => 'str_pad',
        'find' => 'mb_strstr',
    ];

    /**
     * Constructor from a string
     *
     * @param string $string String literal
     * @param string $encoding Optional string encoding. Default autodetects.
     */
    function __construct($string = '', $encoding = false)
    {

        if (!$encoding) {
            $this->encoding = mb_detect_encoding($string);
        } else {
            $this->encoding = $encoding;
        }

        $this->string = (string)iconv($encoding, 'UTF-8', $string);
    }

    /**
     * Syntax sugar for construc a new object
     */
    public static function new($string = '', $encoding = false)
    {
        return new self($string, $encoding);
    }

    /**
     * Call PHP functions over the string.
     */
    private function callFunction($function, $args = []): self
    {
        // Si existe un equivalente en $this->functions, lo
        // usamos.
        if (isset($this->functions[$function])) {
            $real_function = $this->functions[$function];
        } else {
            $real_function = $function;
        }

        // Existe la función?
        if (!is_callable($real_function)) {
            throw new BadFunctionCallException("'$function' is not a valid Str method.");
        }

        // Colocamos la cadena como primer paráemtro
        array_unshift($args, $this->string);

        // Llamamos a la función
        $res = call_user_func_array($real_function, $args);

        // Si lo que devuelve es un string, lo reencodeamos
        if (is_string($res)) {
            return new self($res);
        } else {
            return $res;
        }
    }

    /**
     * Magig method to catch a Str function call.
     */
    function __call($function, $args = [])
    {
        return $this->callFunction($function, $args);
    }


    /**
     * Appends a string
     *
     * @param string $string String to append
     */
    public function append($string)
    {
        return new self($this->string . (string)$string);
    }

    /**
     * Prepends a string
     *
     * @param string $string String to prepend
     */
    public function prepend($string)
    {
        return new self((string)$string . $this->string);
    }

    /**
     * Insert a string in a position
     *
     * @param integer $position Character where to insert
     * @param string $string String to insert
     */
    public function insert($position, $string)
    {
        return new self((string)($this(0, $position) . $string . $this($position)));
    }

    /**
     * Shortcut for left-padding the string
     *
     * @param int $length Padding lenght
     * @param string $fill Fill character
     */
    public function padLeft($length, $fill = " ")
    {
        return $this->pad($length, $fill, STR_PAD_LEFT);
    }

    /**
     * Shortcut for right-padding the string
     *
     * @param int $length Padding lenght
     * @param string $fill Fill character
     */
    public function padRight($length, $fill = " ")
    {
        return $this->pad($length, $fill, STR_PAD_RIGHT);
    }

    /**
     * Shortcut for both-padding (centering) the string
     *
     * @param int $length Padding lenght
     * @param string $fill Fill character
     */
    public function padBoth($length, $fill = " ")
    {
        return $this->pad($length, $fill, STR_PAD_BOTH);
    }

    /**
     * Replace a substring for another
     *
     * @param string $from Cadena a buscar
     * @param string $to Cadena a reemplazar
     */
    public function replace($from, $to, $count = null)
    {
        return new self(str_replace($from, $to, $this->string, $count));
    }

    /**
     * Applies a sprintf() function to the string
     *
     * @param mixed Variadic positional replace values
     */
    public function sprintf(...$args)
    {
        return new self(sprintf($this->string, ...$args));
    }

    /**
     * Sets the first letter as uppercase
     */
    public function firstToUpper()
    {
        return new self($this(0, 1)->toUpper() . $this(1));
    }

    /**
     * Magic method to perform a substring
     */
    public function __invoke(...$args)
    {
        return $this->callFunction('mb_substr', $args);
    }

    /**
     * Returns the string
     *
     * @return string
     */
    function __toString() {
        return $this->string;
    }

    /***** IMPLEMENTACIÓN DEL ARRAYACCESS *****/
    public function offsetExists($offset): bool
    {
        return $offset >= 0 && $offset < mb_strlen($this->string);
    }

    public function offsetGet($offset): mixed
    {
        return new self(mb_substr($this->string, $offset, 1 ));
    }

    public function offsetSet($offset, $value): void
    {

        // $value debe ser un string
        $value = (string)$value;

        $this->string =
            mb_substr($this->string, 0, $offset) .
            $value .
            mb_substr($this->string, $offset + 1);
    }

    public function offsetUnset($offset): void
    {
        $this->string =
            mb_substr($this->string, 0, $offset) .
            mb_substr($this->string, $offset + 1);

    }

    public function current(): mixed
    {
        return $this[$this->iter_index];
    }

    public function key(): mixed
    {
        return $this->iter_index;
    }

    public function next(): void
    {
        $this->iter_index++;
    }

    public function rewind(): void
    {
        $this->iter_index = 0;
    }

    public function valid(): bool
    {
        return $this->offsetExists($this->iter_index);
    }
}
