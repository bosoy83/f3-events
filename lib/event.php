<?php

/**
 * Event System for PHP Fat-Free Framework
 *
 * The contents of this file are subject to the terms of the GNU General
 * Public License Version 3.0. You may not use this file except in
 * compliance with the license. Any of the license terms and conditions
 * can be waived if you get permission from the copyright holder.
 *
 * Copyright (c) 2017 ~ ikkez
 * Christian Knuth <ikkez0n3@gmail.com>
 * https://github.com/ikkez/F3-Sugar/
 *
 * @version 0.5.2
 * @date: 01.11.2015
 **/
class Event extends Prefab {

	protected $f3;
	protected $ekey;

	/**
	 * Event constructor.
	 * @param null $obj
	 */
	public function __construct($obj=null) {
		/** @var \Base $f3 */
		$this->f3 = \Base::instance();
		if ($obj)
			$this->ekey = 'EVENTS_local.'.$this->f3->hash(spl_object_hash($obj)).'.';
		else
			$this->ekey = 'EVENTS.';
	}

	/**
	 * add a listener to an event. It can catch the event dispatched by broadcast and emit.
	 * @param $key
	 * @param $func
	 * @param int $priority
	 * @param array $options
	 */
	public function on($key,$func,$priority=10,$options=array()) {
		$call = $options ? array($func,$options) : $func;
		if ($e = &$this->f3->ref($this->ekey.$key))
			$e[$priority][] = $call;
		else
			$e = array($priority=>array($call));
		krsort($e);
	}

	/**
	 * remove event listener and its children
	 * @param $key
	 */
	public function off($key) {
		$this->f3->clear($this->ekey.$key);
	}

	/**
	 * check if there is an event key existing
	 * @param $key
	 * @return bool
	 */
	public function has($key) {
		return $this->f3->exists($this->ekey.$key);
	}

	/**
	 * Dispatches an event to all descendants of $key and notify their registered listeners.
	 * Afterwards the event traverses down towards the next children.
	 * The event cannot be canceled, but additional listeners
	 * on the same event name can be skipped if one listener returns false and $hold==true
	 * @param string $key
	 * @param mixed $args
	 * @param array $context
	 * @param bool|true $hold
	 * @return mixed
	 */
	public function broadcast($key, $args=null, &$context=array(), $hold=true) {
		if (!$this->f3->exists($this->ekey.$key, $e))
			return $args;
		$descendants=array();
		foreach($e as $nkey=>$nval)
			if (is_string($nkey))
				$descendants[] = $nkey;
		if ($descendants)
			foreach($descendants as $dkey) {
				if ($this->f3->exists($this->ekey.$key.'.'.$dkey, $e)) {
					$listeners = array();
					$ev=array(
						'name'=>$key.'.'.$dkey,
						'key'=>$dkey
					);
					foreach($e as $nkey=>$nval)
						if (is_numeric($nkey))
							$listeners = array_merge($listeners,array_values($e[$nkey]));
					if ($listeners)
						foreach ($listeners as $func) {
							if (!is_array($func))
								$func = array($func,array());
							$ev['options']=$func[1];
							$out = $this->f3->call($func[0],array($args,&$context,$ev));
							if ($hold && $out===FALSE)
								break;
							if ($out)
								$args = $out;
						}
					$args = $this->broadcast($key.'.'.$dkey,$args,$context,$hold);
				}
			}
		return $args;
	}

	/**
	 * dispatches an event name upwards through the hierarchy and notify the registered listeners.
	 * The event life cycle starts at the $key event and calls all own listeners.
	 * Afterwards all sub-events are notified, then the event traverses upwards to the root and
	 * calls all listeners along the way. The event will stop propagating if one of the listeners
	 * cancels it on the way to the top.
	 * @param string $key
	 * @param mixed $args
	 * @param array $context
	 * @param bool $hold
	 * @return mixed
	 */
	public function emit($key, $args=null, &$context=array(), $hold=true) {
		$nodes = explode('.',$key);
		foreach ($nodes as $i=>$slot) {
			$key = implode('.',$nodes);
			array_pop($nodes);
			$expl = explode('.',$key);
			$ev = array(
				'name' => $key,
				'key' => array_pop($expl)
			);
			if ($this->f3->exists($this->ekey.$key,$e) && !empty($e)) {
				$listeners=array();
				foreach ($e as $nkey=>$nval)
					if (is_numeric($nkey))
						$listeners = array_merge($listeners,array_values($e[$nkey]));
				if ($listeners)
					foreach ($listeners as $func) {
						if (!is_array($func))
							$func = array($func,array());
						$ev['options']=$func[1];
						$out = $this->f3->call($func[0],array($args,&$context,$ev));
						if ($hold && $out===FALSE)
							return $args;
						if ($out)
							$args = $out;
					}
			}
			if ($i==0) {
				$args = $this->broadcast($key,$args,$context);
				continue;
			}
		}
		return $args;
	}

	/**
	 * attach event sensor to a local object
	 * @param $obj
	 * @return Event
	 */
	public function watch($obj) {
		return new self($obj);
	}

	/**
	 * drop the watching sensor for an object
	 * @param $obj
	 */
	public function unwatch($obj) {
		$this->f3->clear('EVENTS_local.'.$this->f3->hash(spl_object_hash($obj)));
	}
}