<!--
    //  @Author: Vu Quang Son
    //  @DateCreated: 27/10/2014
    /*
    * To change this template, choose Tools | Templates
    * and open the template in the editor.
    */
-->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        @yield('head')
    </head>
    
    <body onLoad="javascript:GetTime();">
        <div id="main-container">
            <!-- Header -->
            <div id="header">
                @include('includes.header')
            </div>
            <!-- End Header -->

            <!-- Container -->
            <div id="container">
                <div class="shell {{$baseUrl}}">
                    
                    @yield('message-handle')
                    
                    <br />
                    <!-- Main -->
                    <div id="main">
                        <div class="cl">&nbsp;</div>

                        <!-- Content -->
                        <div id="content" class="{{$baseUrl}}-container">
                            @yield('content')
                        </div>
                        <!-- End Content -->

                        <div class="cl">&nbsp;</div>			
                    </div>
                    <!-- Main -->
                </div>
            </div>
            <!-- End Container -->
        </div>
        <!-- Footer -->
        <div id="footer">
            @include('includes.footer')
        </div>
    </body>
</html>    
