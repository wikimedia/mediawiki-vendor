<?php
namespace Elastica\Processor;

class Attachment extends AbstractProcessor
{
    /**
     * Attachment constructor.
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

    /**
     * Set target_field. Default attachment.
     *
     * @param string $targetField
     *
     * @return $this
     */
    public function setTargetField($targetField)
    {
        return $this->setParam('target_field', $targetField);
    }

    /**
     * Set indexed_chars. Default 100000.
     *
     * @param int $indexedChars
     *
     * @return $this
     */
    public function setIndexedChars($indexedChars)
    {
        return $this->setParam('indexed_chars', $indexedChars);
    }

    /**
     * Set properties. Default all properties. Can be content, title, name, author, keywords, date, content_type, content_length, language.
     *
     * @param array $properties
     *
     * @return $this
     */
    public function setProperties(array $properties)
    {
        return $this->setParam('properties', $properties);
    }

    /**
     * Set ignore_missing. Default false.
     *
     * @param bool $ignoreMissing
     *
     * @return $this
     */
    public function setIgnoreMissing($ignoreMissing)
    {
        return $this->setParam('ignore_missing', $ignoreMissing);
    }
}
