<?php namespace SmashPig\Core\Jobs;

use SmashPig\Core\DataStores\JsonSerializableObject;
use SmashPig\Core\Runnable;

/**
 * @deprecated New code should implement SmashPig\Core\Runnable instead.
 */
abstract class RunnableJob
	extends JsonSerializableObject
	implements Runnable
{
	public function __construct() {
		parent::__construct();
		$this->{'php-message-class'} = get_called_class();
	}

	abstract public function execute();
}
