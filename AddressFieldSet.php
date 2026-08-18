<?php namespace johnbarrett\Google_Address_Autocomplete;

/**
 * One configured address field set: a search box and the REDCap fields it fills.
 *
 * REDCap hands sub-settings back as a plain array per configured set. This turns one of
 * those into a checked, named shape once, at the boundary. Readonly because a set is
 * configuration — read many times while emitting a page, never modified.
 */
final readonly class AddressFieldSet
{
	public function __construct(
		/** Position in config.json, 0-based. Fixes this set's element-id prefix. */
		public int $index,
		public string $description,
		public bool $disabled,
		/** Instruments this set applies to. Empty means "any form with the source field". */
		public array $forms,
		/** The field the search box attaches to. A set without one is not configured. */
		public string $autocomplete,
		public string $streetNumber,
		public string $street,
		public string $city,
		public string $county,
		public string $state,
		public string $zip,
		public string $country,
		public string $latitude,
		public string $longitude,
		public string $placeName,
		public bool $recoverUnit,
		public string $regionCodes,
		public string $primaryTypes,
	) {}

	/**
	 * Build a set from one raw REDCap sub-setting array.
	 *
	 * Sub-settings are stored flat, one parallel array per child key, so a key added to
	 * config.json after a project was configured is simply absent from the array REDCap
	 * returns. No parameter here may become required, or a routine settings addition
	 * becomes a fatal on already-configured projects.
	 */
	public static function fromSubSetting(array $raw, int $index): self
	{
		return new self(
			index:        $index,
			description:  self::text($raw, 'set-description'),
			disabled:     !empty($raw['set-disabled']),
			forms:        self::formList($raw, 'set-form'),
			// Not trimmed: emitted as the lookup name, so it must match exactly.
			autocomplete: (string)($raw['set-autocomplete'] ?? ''),
			streetNumber: self::text($raw, 'set-street-number'),
			street:       self::text($raw, 'set-street'),
			city:         self::text($raw, 'set-city'),
			county:       self::text($raw, 'set-county'),
			state:        self::text($raw, 'set-state'),
			zip:          self::text($raw, 'set-zip'),
			country:      self::text($raw, 'set-country'),
			latitude:     self::text($raw, 'set-latitude'),
			longitude:    self::text($raw, 'set-longitude'),
			placeName:    self::text($raw, 'set-place-name'),
			recoverUnit:  !empty($raw['set-recover-unit']),
			regionCodes:  (string)($raw['set-region-codes'] ?? ''),
			primaryTypes: (string)($raw['set-primary-types'] ?? ''),
		);
	}

	/** A missing key and a key holding whitespace both mean "not mapped". */
	private static function text(array $raw, string $key): string
	{
		return trim((string)($raw[$key] ?? ''));
	}

	/** A repeatable form-list, which REDCap returns as a bare scalar for a single entry. */
	private static function formList(array $raw, string $key): array
	{
		$forms = $raw[$key] ?? [];
		if (!is_array($forms)) { $forms = [$forms]; }

		$forms = array_map(trim(...), array_map(strval(...), $forms));

		return array_values(array_filter($forms, static fn(string $form): bool => $form !== ''));
	}

	/** A set with no source field was added in the configuration dialog but never filled in. */
	public function isActive(): bool
	{
		return !$this->disabled && $this->sourceKey() !== '';
	}

	/** The source field trimmed, for identity comparisons only. */
	public function sourceKey(): string
	{
		return trim($this->autocomplete);
	}

	/** No configured instrument means "any form with the source field"; the script guards that. */
	public function appliesTo(string $instrument): bool
	{
		return !$this->forms || in_array($instrument, $this->forms, true);
	}

	/** How this set identifies itself in the browser console. */
	public function label(): string
	{
		return '#' . ($this->index + 1) . ($this->description !== '' ? ' ' . $this->description : '');
	}

	/** Element-id prefix. Unique per set — this is what keeps two sets apart. */
	public function elementPrefix(): string
	{
		return 'googleSearch_' . $this->index . '_';
	}
}
