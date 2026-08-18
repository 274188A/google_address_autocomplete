<?php namespace johnbarrett\Google_Address_Autocomplete;

/**
 * The case value is the Google component type, which doubles as the googleSearch_*
 * element-id suffix. Latitude and longitude are not cases: they are found by field name.
 *
 * Do not add subpremise. componentForm doubles as the registry of components that have a
 * destination element, and no googleSearch_*subpremise element is ever created, so an
 * entry would only log "Could not find the element". extractUnitParts() handles the unit.
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
	 * Null means "not an address component": place_name comes from place.displayName, so it
	 * must never appear in componentForm. fillInAddress() clears and refills it explicitly.
	 */
	public function format(): ?string
	{
		return match ($this) {
			self::Route, self::Locality, self::Country => 'longText',
			self::StreetNumber, self::County, self::State, self::PostalCode => 'shortText',
			self::PlaceName => null,
		};
	}

	public static function addressComponents(): array
	{
		return array_values(array_filter(
			self::cases(),
			static fn(self $component): bool => $component->format() !== null
		));
	}
}
