/**
 * Created by dean on 20/11/2014.
 */

app.service('restAngularService', function ($q, $timeout, $rootScope, Restangular) {
  var deferred;

  /*
   * @param params contain :
   *   @param   api     Api link to query to server.
   *   @param   param   Parameter to set to api
   * */
  this.getReceiptLists = function (params, callback) {
    /*
     * if the previous request on running.
     * We kill all previous request.
     */

    if (deferred) deferred.resolve();

    //make defer variable to get promise
    deferred = $q.defer();

    //Return Restangular object
    return Restangular.one(params.api).withHttpConfig({timeout: deferred.promise}).getList('', params.param);

  }

  /**
   * @params params
   */
  this.customGetApi = function (params) {
    /*
     * if the previous request on running.
     * We kill all previous request.
     */
    if (deferred) deferred.resolve();

    //make defer variable to get promise
    deferred = $q.defer();

    //Return Restangular object
    return Restangular
      .one(params.api)
      .customGET(params.param.route);
  }

  /**
   * @params params
   */
  this.getReceiptById = function (params) {
    /*
     * if the previous request on running.
     * We kill all previous request.
     */
    if (deferred) deferred.resolve();

    //make defer variable to get promise
    deferred = $q.defer();

    //Return Restangular object
    return Restangular
      .one(params.api)
      .withHttpConfig({timeout: deferred.promise})
      .get(params.param);
  }

});
