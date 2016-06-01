/**
 * Created by dean on 09/10/2014.
 */

(function (window, undefined) {
    /*
     *
     * Module works with Push Server.
     * contain function to upload file, download file, etc ...
     * Code convention follow Module Design Pattern
     * This pattern is used to mimic classes in conventional software engineering and focuses on public and private access to methods & variables
     *
     * */
    function socketPushModule() {
        /*
         *  All function refers to the instance of socketPushModule when created
         */

        /**
         * Function upload file to push server via Ajax
         *
         * @param param             Contain all of information to connect, upload, etc...
         * @param callbackProgess   Function callback when file upload.
         * @param callbackSucess    Function callback when file finish upload.
         * @param callbackError     Function callback when upload process is error.
         */

        this.uploadPushServer = function uploadPushGun(param, callbackProgess, callbackSuccess, callbackError) {

            param = param || {};

            /**
             * Parameter :
             *     @param fieldFile         Is name of input field type = file, contain file when click upload.
             *     @param obFiles           File to upload.
             *     @param auth              Authentication string, return when user logged in.
             *     @param socketIdentifier  Socket Indentifier string, return when first connect to push server.
             *     @param urlApi            IP or domain name of push server.
             *
             * Set variable.
             * */

            this.fieldFile = param.fieldFile;
            this.obFiles = param.obFiles;
            this.auth = param.auth;
            this.socketIdentifier = param.socketIdentifier;
            this.urlApi = param.urlApi;

            //Create new form data to append
            var formData = new FormData();

            //Fetch data, add to form data prepare send
            this.obFiles.forEach(function(files){
                formData.append(this.fieldFile, files.fileData, files.unqName);
            });

            //create http request prepare to upload file
            var xhr = new XMLHttpRequest();

            //open XMLHttpRequest.
            xhr.open('POST', this.urlApi, true);

            xhr.setRequestHeader('X-Authentication-Token', this.auth);

            //set request parameter
            //xhr.setRequestHeader('X-Socket-Identifier', this.socketIdentifier);

            //Event to return percent progess upload.
            xhr.upload.addEventListener('progress', function (e) {
                if (typeof callbackProgess != "undefined" && callbackProgess) {
                    //Count percent of process
                    this.process = Math.round((e.loaded / e.total) * 100);
                    this.loaded  = (e.loaded / 1024 / 1024);
                    this.total   =  (e.total / 1024 / 1024);
                    callbackProgess(this.process, this.loaded, this.total);
                }
            }, false);

            // Set up a handler for when the request finishes.
            xhr.onload = function (e) {
                if (xhr.status === 200) {
                    if (typeof callbackSuccess != "undefined" && callbackSuccess) {
                        callbackSuccess(e);
                    }
                } else {
                    if (typeof callbackError != "undefined" && callbackError) {
                        callbackError(e);
                    }
                }
            };

            //Send data.
            xhr.send(formData);
        }


        //callback process upload data
        function callbackProgess(progess, loaded, total) {
            return progess, loaded, total;
        }

        //callBack finish upload data
        function callbackSuccess(e) {
            return e;
        }

        //callback when process error
        function callbackError(e) {
            return e;
        }

    }

    // Expose access to the constructor
    window.socketPushModule = socketPushModule;

})(window);
