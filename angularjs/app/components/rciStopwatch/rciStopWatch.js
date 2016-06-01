var Stopwatch = function (selector) {
    var s;
    return {
        settings: {
            stop: 0,
            sw: document.querySelectorAll(selector)[0],
            mills: 0,
            secs: 0,
            mins: 0,
            i: 1,
            times: ["00:00:00"]
        },
        init: function () {
            s = this.settings;
            setInterval(this.timer, 1);
        },
        reset: function () {
            s = this.settings;
            s.mills = 0,
                s.secs = 0,
                s.mins = 0;
            s.sw.innerHTML = ("0" + s.mins).slice(-2) + ":"
                + ("0" + s.secs).slice(-2) + ":"
                + ("0" + s.mills).slice(-2);
        },
        restart: function () {
            s.mills = 0,
                s.secs = 0,
                s.mins = 0;
            this.start();
        },
        start: function () {
            s.stop = 0;
        },
        stop: function () {
            s.stop = 1;
        },
        timer: function () {
            if (s.stop === 0) {
                if (s.mills === 100) {
                    s.secs++;
                    s.mills = 0;
                }
                if (s.secs === 60) {
                    s.mins++;
                    s.secs = 0;
                }
                s.sw.innerHTML = ("0" + s.mins).slice(-2) + ":"
                    + ("0" + s.secs).slice(-2) + ":"
                    + ("0" + s.mills).slice(-2);
                s.mills++;
            }
        }
    };
};