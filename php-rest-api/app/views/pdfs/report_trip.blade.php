<html>
	<head>
		<style>
			h1, h2, h3, h4 {
				text-align: center;
			}
			table {
				text-align: left;
			}
			.pheading {
				text-align: center;
			}
			.pheading.bold {
				font-weight: bold;
			}
			.report-title {
				text-transform: capitalize;
			}
			.center-aligned {
				text-align: center;
			}
			.right-aligned {
				text-align: right;
			}
			.bordered-table th {
				border: 1px solid #000;
			}
			.bordered-table td {
				border: 1px solid #000;
			}
			.bold {
				font-weight: bold;
			}
			.no-border th {
				border: none;
			}
			.no-border td {
				border: none;
			}
		</style>
	</head>
	<body>
		<!-- Trips assigned to the report -->
		<h2>
			Trip {{ $trip->key }}: {{ $trip->Name }}&nbsp;&nbsp;
			@if ($trip->Leg)
				Leg {{ $trip->Leg }}
			@endif
		</h2>

		<table>
			<tr>
				<td width="15%">Start Date:</td>
				<td>{{ date('d-M-Y', strtotime($trip->StartDate)) }}</td>
			</tr>
			<tr>
				<td>End Date:</td>
				<td>{{ date('d-M-Y', strtotime($trip->EndDate)) }}</td>
			</tr>
			<tr>
				<td>Trip State:</td>
				<td>{{ $trip->State }}</td>
			</tr>
		</table>

		<br/><br/>

		<table class="bordered-table">
			<tr style="background-color: #CCC">
				<th width="16%">Trip {{ $trip->key }}</th>
				<th class="right-aligned" width="12%">Amount</th>
				<th class="center-aligned" width="50.5%">Trip#</th>
				<th class="right-aligned" width="12%">Claimed</th>
				<th class="right-aligned" width="12.5%">Approved</th>
			</tr>
			<tr>
				<td>{{ $trip->Name }}</td>
				<td class="right-aligned">{{ number_format($trip->Amount, 2) }}</td>
				<td class="center-aligned">{{ $trip->Reference }}</td>
				<td class="right-aligned">{{ number_format($trip->Claimed, 2) }}</td>
				<td class="right-aligned">{{ number_format($trip->Approved, 2) }}</td>
			</tr>
		</table>

		<br/><br/><br/>

		<table class="bordered-table">
			<tr style="background-color: #CCC">
				<th width="16%">Category</th>
				<th class="right-aligned" width="12%">Amount</th>
				<th width="12%">Item</th>
				<th width="15%">Merchant</th>
				<th class="center-aligned" width="8%">Date</th>
				<th width="15.5%">Memo</th>
				<th class="right-aligned" width="12%">Claimed</th>
				<th class="right-aligned">Approved</th>
			</tr>
			@if (count($trip->Items))
				@foreach ($trip->Items as $item)
					<tr>
						<td>{{ Category::getParentsString($item->CategoryID, $item->CategoryName) }}</td>
						<td class="right-aligned">{{ number_format($item->Amount, 2) }}</td>
						<td>{{ $item->Name }}</td>
						<td>
							{{ $item->MerchantName }}<br/>
							@if ($item->ReceiptImage)
								{{ $trip->Reference }} - Receipt{{ str_pad($item->ReceiptImage->Number, 2, '0', STR_PAD_LEFT) }}.{{ $item->ReceiptImage->FileExtension }}
							@elseif ($item->RawData)
								{{ $trip->Reference }} - Receipt{{ str_pad($item->RawData->Number, 2, '0', STR_PAD_LEFT) }}.jpg
							@endif
						</td>
						<td class="center-aligned">{{ date('d-M-Y', strtotime($item->PurchaseTime)) }}</td>
						<td>
							@if (count($item->ReportMemos))
								@foreach ($item->ReportMemos as $memo)
									{{ $memo->SenderType }}: {{ $memo->CreatedDate }}<br/>
									{{ $memo->Message }}
								@endforeach
							@endif
						</td>
						<td class="right-aligned">{{ number_format($item->Claimed, 2) }}</td>
						<td class="right-aligned">{{ number_format($item->Approved, 2) }}</td>
					</tr>
				@endforeach
			@endif
		</table>
	</body>
</html>