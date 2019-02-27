<?php
namespace Elastica\Processor;

/**
 * Elastica Append Processor.
 *
 * @author   Federico Panini <fpanini@gmail.com>
 *
 * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/append-processor.html
 */
class Append extends AbstractProcessor
{
    /**
     * Append constructor.
     *
     * @param string       $field field name
     * @param string|array $value field values to append
     */
    public function __construct($field, $value)
    {
        $this->setField($field);
        $this->setValue($value);
    }

    /**
     * Set field.
     *
     * @param string $field
     *
     * @return $this
     */
    public function setField($field)
    {
        return $this->setParam('field', $field);
    }

    /**
     * Set field value.
     *
     * @param string|array $value
     *
     * @return $this
     */
    public function setValue($value)
    {
        return $this->setParam('value', $value);
    }
}
