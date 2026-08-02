<?php namespace johnbarrett\Google_Address_Autocomplete;

/**
 * A Google address component the module can write into a REDCap field.
 *
 * The case value is the Google address component type, which doubles as the
 * googleSearch_* element-id suffix — so this drives both the id assignment and
 * the component lookup on the client.
 *
 * This exists to keep two lists in step. The PHP needed a component type =>
 * destination-field map, and the emitted JavaScript needed a component type =>
 * Place API property map (componentForm), which used to be seven hand-written
 * conditional lines. They had to agree, and nothing enforced it: the two lists
 * had already drifted into different orders. Both are now generated from here.
 *
 * Latitude and longitude are deliberately NOT cases. They are looked up by field
 * NAME rather than by googleSearch_* id (see updateValue in the emitted script),
 * so they are emitted separately and are not part of this map.
 *
 * Do NOT add subpremise. componentForm doubles as the registry of "components
 * with a destination element", and every entry is cleared through
 * updateValue(autocompletePrefix + type) on each selection. No
 * googleSearch_*subpremise element is ever created, so an entry would only log
 * "Could not find the element" every time. extractUnitParts() handles the unit.
 */
enum AddressComponent: string
{
	case StreetNumber = 'street_number';
	case Route        = 'route';
	case Locality     = 'locality';
	case County       = 'administrative_area_level_2';
	case State        = 'administrative_area_level_1';
	case PostalCode   = 'postal_code';
	case Country      = 'country';
	case PlaceName    = 'place_name';

	/** The AddressFieldSet property holding this component's REDCap field name. */
	public function property(): string
	{
		return match ($this) {
			self::StreetNumber => 'streetNumber',
			self::Route        => 'street',
			self::Locality     => 'city',
			self::County       => 'county',
			self::State        => 'state',
			self::PostalCode   => 'zip',
			self::Country      => 'country',
			self::PlaceName    => 'placeName',
		};
	}

	/**
	 * Which Place API property to read off the address component — the value side
	 * of the emitted componentForm map.
	 *
	 * Null means "not an address component at all": place_name is mapped from
	 * place.displayName, not from addressComponents, so it must never appear in
	 * componentForm. If it did, the clear loop in fillInAddress() would blank the
	 * field on every selection while only refilling it when a display name exists
	 * — silently wiping a field that previously kept its value.
	 */
	public function format(): ?string
	{
		return match ($this) {
			self::Route, self::Locality, self::Country => 'longText',
			self::StreetNumber, self::County, self::State, self::PostalCode => 'shortText',
			self::PlaceName => null,
		};
	}

	/** The cases that belong in componentForm, i.e. those read from addressComponents. */
	public static function addressComponents(): array
	{
		return array_values(array_filter(
			self::cases(),
			static fn(self $component): bool => $component->format() !== null
		));
	}
}
