<?php namespace johnbarrett\Google_Address_Autocomplete;

/**
 * A Google address component the module can write into a REDCap field. Both the PHP
 * destination-field map and the emitted componentForm map are generated from here, so
 * the two cannot drift apart.
 *
 * The case value is the Google component type, which doubles as the googleSearch_*
 * element-id suffix. Latitude and longitude are not cases: they are looked up by field
 * name instead, so they are emitted separately.
 *
 * Do not add subpremise. componentForm doubles as the registry of components that have
 * a destination element, and every entry is cleared on each selection, so an entry with
 * no element would only log "Could not find the element". extractUnitParts() handles it.
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
	 * The Place API property to read off the component — the value side of componentForm.
	 *
	 * Null means "not an address component": place_name comes from place.displayName, so
	 * it must never appear in componentForm. fillInAddress() clears and refills it
	 * explicitly, so a name captured for one selection cannot stay on a different address.
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
