<?php namespace SmashPig\Core\DataStores;

/**
 * Typically thrown when a data constraint is not followed on a serializable object. This
 * can be on either serialization or deserialization.
 */
class DataSerializationException extends DataStoreException {
}
