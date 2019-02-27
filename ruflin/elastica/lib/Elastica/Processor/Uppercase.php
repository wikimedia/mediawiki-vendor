<?php
namespace Elastica\Processor;

/**
 * Elastica Uppercase Processor.
 *
 * @author   Federico Panini <fpanini@gmail.com>
 *
 * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/uppercase-processor.html
 */
class Uppercase extends AbstractProcessor
{
    /**
     * Uppercase constructor.
     *
     * @param string $field
     */
    public function __construct($field)
    {
        $this->setField($field);
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
}
