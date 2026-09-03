<?php namespace johnbarrett\Google_Address_Autocomplete;

/**
 * One configured address field set. REDCap hands sub-settings back as a plain array per
 * set; this turns one of those into a checked, named shape once, at the boundary.
 */
final readonly class AddressFieldSet
{
	public function __construct(
		/** Position in config.json, 0-based. Fixes this set's element-id prefix. */
		public int $index,
		public string $description,
		public bool $disabled,
		public array $forms,
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
	 * Sub-settings are stored flat, one parallel array per child key, so a key added to
	 * config.json after a project was configured is simply absent from the array REDCap
	 * returns. No parameter here may become required.
	 */
	public static function fromSubSetting(array $raw, int $index): self
	{
		return new self(
			index:        $index,
			description:  self::text($raw, 'set-description'),
			disabled:     !empty($raw['set-disabled']),
			forms:        self::formList($raw, 'set-form'),
			// Trimmed like every other field name: a trailing space typed into the setting
			// would otherwise pass every server-side check and then match no element.
			autocomplete: self::text($raw, 'set-autocomplete'),
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

	public function isActive(): bool
	{
		return !$this->disabled && $this->autocomplete !== '';
	}

	public function appliesTo(string $instrument): bool
	{
		return !$this->forms || in_array($instrument, $this->forms, true);
	}

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
