<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| Validation Language Lines
	|--------------------------------------------------------------------------
	|
	| The following language lines contain the default error messages used by
	| the validator class. Some of these rules have multiple versions such
	| such as the size rules. Feel free to tweak each of these messages.
	|
	*/

	"accepted"         => "The :attribute must be accepted.",
	"active_url"       => "The :attribute is not a valid URL.",
	"after"            => "The :attribute must be a date after :date.",
	"alpha"            => "The :attribute may only contain letters.",
	"alpha_dash"       => "The :attribute may only contain letters, numbers, and dashes.",
	"alpha_num"        => "The :attribute may only contain letters and numbers.",
	"before"           => "The :attribute must be a date before :date.",
	"between"          => array(
		"numeric" => "The :attribute must be between :min - :max.",
		"file"    => "The :attribute must be between :min - :max kilobytes.",
		"string"  => "The :attribute must be between :min - :max characters.",
	),
	"confirmed"        => "The :attribute confirmation does not match.",
	"date"             => "The :attribute is not a valid date.",
	"date_format"      => "The :attribute does not match the format :format.",
	"different"        => "The :attribute and :other must be different.",
	"digits"           => "The :attribute must be :digits digits.",
	"digits_between"   => "The :attribute must be between :min and :max digits.",
	"email"            => "The :attribute format is invalid.",
	"exists"           => "The selected :attribute is invalid.",
	"image"            => "The :attribute must be an image.",
	"in"               => "The selected :attribute is invalid.",
	"integer"          => "The :attribute must be an integer.",
	"ip"               => "The :attribute must be a valid IP address.",
	"max"              => array(
		"numeric" => "The :attribute may not be greater than :max.",
		"file"    => "The :attribute may not be greater than :max kilobytes.",
		"string"  => "The :attribute may not be greater than :max characters.",
	),
	"mimes"            => "The :attribute must be a file of type: :values.",
	"min"              => array(
		"numeric" => "The :attribute must be at least :min.",
		"file"    => "The :attribute must be at least :min kilobytes.",
		"string"  => "The :attribute must be at least :min characters.",
	),
	"not_in"           => "The selected :attribute is invalid.",
	"numeric"          => "The :attribute must be a number.",
	"regex"            => "The :attribute format is invalid.",
	"required"         => "The :attribute field is required.",
	"required_if"      => "The :attribute field is required when :other is :value.",
	"required_with"    => "The :attribute field is required when :values is present.",
	"required_without" => "The :attribute field is required when :values is not present.",
	"same"             => "The :attribute and :other must match.",
	"size"             => array(
		"numeric" => "The :attribute must be :size.",
		"file"    => "The :attribute must be :size kilobytes.",
		"string"  => "The :attribute must be :size characters.",
	),
	"unique"           => "The :attribute has already been taken.",
	"url"              => "The :attribute format is invalid.",
	
	/*
	|--------------------------------------------------------------------------
	| Messages for Custom Validators
	|--------------------------------------------------------------------------
	*/
	"password_matched" => "Your input for current password is incorrect.",
	"item_required" => "You need to input at least one item.",
	"receipts_belong_to" => "Specified receipts do not belong to the user who sent this request.",
	"items_belong_to" => "Specified items do not belong to the user who sent this request.",
	"trips_belong_to" => "Specified trips do not belong to the user who sent this request.",
	"trip_not_added" => "Some trips was added to the specified report.",
	"trip_date" => "You cannot have a trip start before the end of another trip on record. Please check your Start Date and try again.",
	"quick_trip_date" => "End date must be equal or greater than start date and selected date must not belongs to an existing trip.",
	'trips_obj_not_added' => "Some trips was added to other reports",
	"reports_belong_to" => "Specified reports do not belong to the user who sent this request.",
	"reports_submitted_by" => "Not all of specified report(s) were submitted by the user who sent this request.",
    "reports_submitted_by_mb" => "Not all of specified report(s) were submitted by the user who sent this request.",
	"reports_approved_by" => "You are not the approver of these specified reports.",
	"multiple_emails" => "One of this email list is not valid.",
	"valid_ext" => "This file is not permitted to be uploaded.",
	"file_size" => "This file is bigger than the file size limit.",
	"is_app" => "This is an invalid application.",
	"belongs_to_app" => "This category does not belong to the specified application.",
	"profile_email" => "This email address is currently used by another user.",
	"extra_emails" => "One or more of the extra email addresses is invalid or currently used by another user.",
	"not_before" => "The End Date must be equal or greater than the Start Date.",
	"receipts_for_submitted_report" => "Some of these receipts are reported. You can not modify or delete them.",
	"items_for_submitted_report" => "Some of these items are reported. You can not modify or delete them.",
	"reports_not_submitted" => "Some of these reports are submitted. You cannot modify or delete them.",
    "reports_not_submitted_mb" => "Some of these reports are submitted. You cannot modify or delete them.",
	"trips_not_reported" => "Some of these trips are reported. You cannot modify or delete them.",
	"report_can_be_submitted" => "This report needs to have an approver and at least one trip to be submitted",
	
	/*
	|--------------------------------------------------------------------------
	| Custom Validation Language Lines
	|--------------------------------------------------------------------------
	|
	| Here you may specify custom validation messages for attributes using the
	| convention "attribute.rule" to name the lines. This makes it quick to
	| specify a specific custom language line for a given attribute rule.
	|
	*/

	'custom' => array(),

	/*
	|--------------------------------------------------------------------------
	| Custom Validation Attributes
	|--------------------------------------------------------------------------
	|
	| The following language lines are used to swap attribute place-holders
	| with something more reader friendly such as E-Mail Address instead
	| of "email". This simply helps us make messages a little cleaner.
	|
	*/

	'attributes' => array(),

);
