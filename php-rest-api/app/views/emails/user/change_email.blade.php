<!DOCTYPE html>
<html lang="en-US">
	<head>
		<meta charset="utf-8">
	</head>
	<body>
		<p>Dear {{ $name }},<p>

		<p>
			You've entered {{ $email }} as the primary email address for your ReceiptClub account. To complete the process, we just need to verify that you are the one who made the change. Simply click the link below to confirm.<br/><br/>
			Verify Now <a href="{{ $url }}">{{ $url }}</a><br/><br/>
			Wondering why you got this email?<br/>
			It's sent when someone changes the primary email address for ReceiptClub account. <br/>
			If you didn't do this, don't worry. The new email address cannot be used as a primary one without your verification.<br/><br/>
			For more information, see our frequently asked questions.<br/><br/>
			Thanks,<br/>
			ReceiptClub Team.
		</p>
	</body>
</html>
