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
        <div class="pheading"><font size="18">Travel Expense Trip</font></div>
        <div class="pheading"><font size="18">{{    $trip->Name }}</font></div>
        <div class="pheading bold"><font size="14">Trip Summary</font><br/><br/></div> 
        <table>
            <tr>
                <td width="15%" class="bold">Trip Number:</td>
                <td width="35%">{{ $trip->Reference }}</td>                
                <td width="17%" class="bold">Report Number:</td>
                <td>{{ ($trip->Report) ? $trip->Report : '' }}</td>                
            </tr>
            <tr>
                <td class="bold">Currency:</td>
                <td>{{ $currency }}</td>		
                <td class="bold">Report name:</td>
                <td>{{ ($trip->ReportTitle) ? $trip->ReportTitle : '' }}</td>
            </tr>
            <tr>
                <td class="bold">Start Date:</td>
                <td>{{ $startDate }}</td>		
                <td class="bold">End Date:</td>
                <td>{{ $endDate }}</td>
            </tr>
            <tr>
                <td class="bold">From</td>
                <td>{{ $trip->Departure }}</td>
                <td class="bold">To</td>
                <td>{{ $trip->Arrival }}</td>
            </tr>

        </table>				

        <br/><br/>

        <h3>{{ $trip->Name}} Trip Financial Summary by Trip</h3>
        <table class="bordered-table">
            <tr style="background-color: #999;">
                <th width="63%">By Trip</th>
                <th class="right-aligned" width="20%">Total Amount</th>
                <th class="right-aligned" width="20%">Claimed</th>
                <th class="right-aligned" width="20%">Approved</th>
            </tr>

            <tr>
                <td>Trip {{ $trip->Name }}</td>
                <td class="right-aligned">${{ number_format($trip->Amount, 2) }}</td>
                <td class="right-aligned">${{ number_format($trip->Claimed, 2) }}</td>
                <td class="right-aligned">${{ number_format($trip->Approved, 2) }}</td>
            </tr>

            <tr class="no-border">
                <td class="right-aligned bold">Total</td>                
                <td class="right-aligned bold">${{ number_format($trip->Amount, 2) }}</td>
                <td class="right-aligned bold">${{ number_format($trip->Claimed, 2) }}</td>
                <td class="right-aligned bold">${{ number_format($trip->Approved, 2) }}</td>
            </tr>
        </table>

        <br/><br/>
        <h3>{{ $trip->Name}} Trip Financial Summary By Expense Category</h3>
        <table class="bordered-table">
            <tr style="background-color: #999;">
                <th width="63%">By Category</th>
                <th class="right-aligned" width="20%">Amount</th>
                <th width="40%" class="right-aligned">% of Total</th>
            </tr>
            @if (count($trip->CategoryItems))
            @foreach ($trip->CategoryItems as $key => $category)
            <tr>
                <td>{{ $category->Name }}</td>
                <td class="right-aligned">${{ number_format($category->Amount, 2) }}</td>
                <td class="right-aligned">{{ $trip->Amount > 0 ? number_format($category->Amount * 100 / $trip->Amount, 2) : '0.00' }}%</td>                
            </tr>
            @endforeach
            @endif
            <tr class="no-border">
                <td class="right-aligned bold">Total</td>                
                <td class="right-aligned bold">${{ number_format($trip->Amount, 2) }}</td>
                <td class="right-aligned bold">100.00%</td>
            </tr>
        </table>
        
        <h3>{{ $trip->Name}} Trip Financial Summary By Items</h3>
        <table class="bordered-table">
            <tr style="background-color: #999;">
                <th width="27%">Category</th>                
                <th width="16%" class="right-aligned">Amount</th>
                <th width="40%" class="right-aligned">Item Name</th>
                <th class="right-aligned">Merchant</th>
                <th class="right-aligned">Purchase</th>                
            </tr>
            @if (count($trip->Items))                        
            @foreach ($trip->Items as $key => $item)                        
            <tr>
                <td>{{ $item->CategoryName }}</td>                               
                <td class="right-aligned">${{ number_format($item->Amount, 2) }}</td>
                <td class="right-aligned">{{ $item->Name }} <br> Receipt @if (isset($item->ReceiptImage->Number)){{ '0'.$item->ReceiptImage->Number }}@endif.@if (isset($item->ReceiptImage->FileExtension)){{ $item->ReceiptImage->FileExtension }}@endif</td>
                <td class="right-aligned">{{ $item->MerchantName }}</td>                
                <td class="right-aligned">{{ date("d-M-Y",strtotime($item->PurchaseTime)) }}</td>
            </tr>
            @endforeach
            @endif                            
        </table>
        
    </body>
</html>
