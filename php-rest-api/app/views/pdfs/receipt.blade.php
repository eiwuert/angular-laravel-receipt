<html>
	<head></head>
	<body>
		<div style="padding-bottom: 10px;">
			<span style="font-weight:bold">From </span>{{ date(' F Y ', strtotime($dateFrom)) }}
			<span style="font-weight:bold">To </span>{{ date('F Y', strtotime($dateTo)) }}
		</div>
		<div style="padding-bottom: 10px;">
			<span style="font-weight:bold">Exported Date: </span>{{ date('m/d/Y') }}
		</div>
		<div style="padding-bottom: 10px;">
			<span style="font-weight:bold">User: </span>{{ $user->Username }}
		</div>
		<div style="padding-bottom: 10px;">
			<span style="font-weight:bold">Total Receipt(s): </span>{{ count($receipts) }}
		</div>
		<table class="receipt-pdf-listing">
			<tr style="background-color:black;color:white;border:1px solid #007700;">
				<th colspan="2" style="width:30%">Merchant</th>
				<th>Amount</th>
				<th style="width:25%">Status</th>
				<th style="width:10%">Tax</th>
				<th style="width:10%">Discount</th>
				<th>Date</th>
			</tr>
			@foreach ($receipts as $receipt)
			<tr style="background-color:#C0C0C0">
				<td style="border-left:1px solid #0C0B0B;border-top:1px solid #0C0B0B;border-bottom:1px solid #0C0B0B;font-weight:bold;" colspan="2">
					{{ htmlspecialchars($receipt->MerchantName) }}
				</td>
				<td style="border-top:1px solid #0C0B0B;border-bottom:1px solid #0C0B0B;">
					{{ $receipt->DigitalTotal }}
				</td>
				<td style="border-top:1px solid #0C0B0B;border-bottom:1px solid #0C0B0B;">
					{{ Receipt::getVerifyStatus($receipt->VerifyStatus) }}
				</td>
				<td style="border-top:1px solid #0C0B0B;border-bottom:1px solid #0C0B0B;">
					{{ $receipt->Tax }}
				</td>
				<td style="border-top:1px solid #0C0B0B;border-bottom:1px solid #0C0B0B;">
					{{ $receipt->Discount }}
				</td>  
				<td style="border-right:1px solid #0C0B0B;border-top:1px solid #0C0B0B;border-bottom:1px solid #0C0B0B;">
					{{ $receipt->PurchaseTime }}
				</td>
			</tr>
				@if (count($receipt->Items))
					@foreach ($receipt->Items as $item)
						<tr>
							<td style="border-left:1px solid #0C0B0B;border-top:1px solid #0C0B0B;border-bottom:1px solid #0C0B0B;width:10%"></td>
							<td style="border-top:1px solid #0C0B0B;border-bottom:1px solid #0C0B0B;width:20%"><div style="font-weight:bold">{{ htmlspecialchars($item->Name) }}</div></td>
							<td style="border-top:1px solid #0C0B0B;border-bottom:1px solid #0C0B0B;">{{ $item->Amount }}</td>
							<td style="border-right:1px solid #0C0B0B;border-top:1px solid #0C0B0B;border-bottom:1px solid #0C0B0B;" colspan="4">{{ Item::getCategorizeStatus($item->CategorizeStatus) }}</td>
						</tr>
					@endforeach
				@endif
			@endforeach
		</table>
	</body>
</html>