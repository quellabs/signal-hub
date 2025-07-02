<?php
	
	namespace Quellabs\Canvas\Sculpt\PublishHelpers;
	
	/**
	 * Represents a planned file operation within a transaction
	 */
	class PlannedOperation {
		public const string TYPE_COPY = 'copy';
		public const string TYPE_SKIP = 'skip';
		public const string TYPE_OVERWRITE = 'overwrite';
		
		public function __construct(
			public readonly string $type,
			public readonly string $sourcePath,
			public readonly string $targetPath,
			public readonly string $reason = '',
			public readonly ?string $backupPath = null
		) {}
	}