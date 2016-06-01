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
			.bordered-table {
				width: 80%;
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
		<div class="pheading"><font size="18">Travel Expense Report</font></div>
		<div class="pheading"><font size="18">{{ $report->Title }}</font></div>
		<div class="pheading bold"><font size="14">Report Summary</font><br/><br/></div> 
		<table>
			<tr>
				<td width="15%" class="bold">Report Number:</td>
				<td width="35%">{{ $report->Reference }}</td>
				<td width="15%" class="bold">Number of trips:</td>
				<td>{{ count($report->Trips) }}</td>
			</tr>
			<tr>
				<td class="bold">Currency:</td>
				<td>{{ $currency }}</td>
				<td class="bold">Report Type:</td>
				<td>{{ $itemTypeText }}</td>
			</tr>
			<tr>
				<td class="bold">Status:</td>
				<td colspan="3">{{ $report->Status }}</td>
			</tr>
		</table>
		<br/><br/>
		<table>
			<tr>
				<td class="bold" width="15%">Submitter:</td>
				<td width="35%">
					{{ $report->SubmitterFirstName . ' ' . $report->SubmitterLastName }}<br/>
					{{ $report->SubmitterCompanyName }}<br/>
				</td>
				<td>({{ $report->SubmitterEmail }})</td>
			</tr>
			@if ($report->ApproverFirstName)
				<tr>
					<td class="bold">Approver:</td>
					<td>
						{{ $report->ApproverFirstName . ' ' . $report->ApproverLastName }}<br/>
						{{ $report->ApproverCompanyName }}<br/>
					</td>
					<td>({{ $report->ApproverEmail }})</td>
				</tr>
			@endif
		</table>
		
		<br/><br/>
		
		<h3>{{ $report->Title}} Financial Summary by Trip</h3>
		<table class="bordered-table">
			<tr style="background-color: #999;">
				<th width="70%">By Trip</th>
				<th class="right-aligned" width="20%">% of Total</th>
				<th class="right-aligned">Amount</th>
			</tr>
			@if (count($report->Trips))
				@foreach ($report->Trips as $key => $trip)
					<tr>
						<td>Trip {{ $key + 1 }} - {{ $trip->Reference }} - {{ $trip->Name }} {{ $trip->Leg ? " leg " . $trip->Leg : '' }}</td>
						<td class="right-aligned">{{ $report->Amount > 0 ? number_format($trip->Amount * 100 / $report->Amount, 2) : '0.00' }}%</td>
						<td class="right-aligned">${{ number_format($trip->Amount, 2) }}</td>
					</tr>
				@endforeach
			@endif
			<tr class="no-border">
				<td class="right-aligned bold">Total</td>
				<td class="right-aligned bold">100.00%</td>
				<td class="right-aligned bold">${{ number_format($report->Amount, 2) }}</td>
			</tr>
		</table>
		
		<br/><br/>
		<h3>{{ $report->Title}} Financial Summary By Expense Category</h3>
		<table class="bordered-table">
			<tr style="background-color: #999;">
				<th width="70%">By Category</th>
				<th class="right-aligned" width="20%">% of Total</th>
				<th class="right-aligned">Amount</th>
			</tr>
			@if (count($report->Categories))
				@foreach ($report->Categories as $key => $category)
					<tr>
						<td>{{ $category->Name }}</td>
						<td class="right-aligned">{{ $report->Amount > 0 ? number_format($category->Amount * 100 / $report->Amount, 2) : '0.00' }}%</td>
						<td class="right-aligned">${{ number_format($category->Amount, 2) }}</td>
					</tr>
				@endforeach
			@endif
			<tr class="no-border">
				<td class="right-aligned bold">Total</td>
				<td class="right-aligned bold">100.00%</td>
				<td class="right-aligned bold">${{ number_format($report->Amount, 2) }}</td>
			</tr>
		</table>
	</body>
</html>
