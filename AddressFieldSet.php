<?php namespace johnbarrett\Google_Address_Autocomplete;

/**
 * One configured address field set: a search box and the REDCap fields it fills.
 *
 * REDCap hands sub-settings back as a plain associative array per configured set.
 * This turns one of those arrays into a checked, named shape exactly once, at the
 * boundary, so the rest of the module reads properties instead of re-deriving the
 * same trim/cast/default on every access.
 *
 * Readonly because a set is configuration: it is read many times while emitting a
 * page and never modified.
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
	 * Every read is defensive, and that is load-bearing rather than merely cautious.
	 * Sub-settings are stored flat, one parallel array per child key, so a key added
	 * to config.json after a project was configured is simply ABSENT from the array
	 * REDCap returns. No parameter here may become required: that would turn a
	 * routine settings addition into a fatal on already-configured projects.
	 */
	public static function fromSubSetting(array $raw, int $index): self
	{
		return new self(
			index:        $index,
			description:  self::text($raw, 'set-description'),
			disabled:     !empty($raw['set-disabled']),
			forms:        self::formList($raw, 'set-form'),
			// Not trimmed: preserved exactly as configured, because it is compared
			// against the claimed-source map and emitted as the lookup name.
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

	/**
	 * A repeatable form-list setting, which REDCap may return as a bare scalar when
	 * only one entry was chosen.
	 */
	private static function formList(array $raw, string $key): array
	{
		$forms = $raw[$key] ?? [];
		if (!is_array($forms)) { $forms = [$forms]; }

		$forms = array_map(trim(...), array_map(strval(...), $forms));

		return array_values(array_filter($forms, static fn(string $form): bool => $form !== ''));
	}

	/**
	 * Whether this set should run at all. A set with no source field was added in the
	 * configuration dialog but never filled in.
	 */
	public function isActive(): bool
	{
		return !$this->disabled && $this->sourceKey() !== '';
	}

	/**
	 * The source field, trimmed, for comparing one set against another.
	 *
	 * The property itself is stored untrimmed because that is the value emitted into
	 * the script and looked up by name; this is only for identity comparisons.
	 */
	public function sourceKey(): string
	{
		return trim($this->autocomplete);
	}

	/**
	 * Whether this set applies to the given instrument. No configured instrument means
	 * "any form containing the source field", which the emitted script still guards.
	 */
	public function appliesTo(string $instrument): bool
	{
		return !$this->forms || in_array($instrument, $this->forms, true);
	}

	/**
	 * How this set identifies itself in the browser console, so two sets on one page
	 * can be told apart when debugging.
	 */
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
