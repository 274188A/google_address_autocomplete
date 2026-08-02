<?php namespace ExternalModules;

/**
 * Minimal stand-in for the REDCap External Modules framework base class, so the
 * module can be exercised outside REDCap.
 *
 * Only the three framework methods the module actually calls are implemented:
 * getProjectSetting(), getSubSettings() and log(). Nothing here models REDCap
 * behaviour beyond what those three need to return.
 *
 * IMPORTANT: this stub deliberately does NOT autoload sibling classes. REDCap's
 * own autoloading of additional classes in a module namespace is unverified, so
 * the module must require_once them itself — if it ever stops doing that, the
 * harness has to fail here rather than paper over it.
 */
abstract class AbstractExternalModule
{
	/** Project-wide settings, keyed by setting name. */
	public array $projectSettings = [];

	/** Repeating sub-setting groups, keyed by group name (e.g. 'address-set'). */
	public array $subSettings = [];

	/** Everything passed to log(), in call order, so fixtures can assert on it. */
	public array $logMessages = [];

	public function getProjectSetting($key, $projectId = null)
	{
		return $this->projectSettings[$key] ?? null;
	}

	public function getSubSettings($key, $projectId = null)
	{
		return $this->subSettings[$key] ?? [];
	}

	public function log($message, $parameters = [])
	{
		$this->logMessages[] = $message;
		return 1;
	}
}
