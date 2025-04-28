<?php
	
	namespace Quellabs\ObjectQuel\EntityManager\Core;
	
	use Quellabs\ObjectQuel\EntityManager\Reflection\BasicEnum;
	
	class DirtyState extends BasicEnum {
		const None = 0;
		const Dirty = 1;
		const New = 2;
		const Deleted = 3;
		const NotManaged = 4;
	}