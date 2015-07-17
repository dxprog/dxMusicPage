export default {

  getTimeParts:function(seconds) {

    var
    h = Math.floor(seconds / 3600),
    m = Math.floor((seconds - h * 3600) / 60),
    s = seconds - h * 3600 - m * 60;
    return {hours:h, minutes:m, seconds:s};

  },

  padNumber:function(num) {
    return num < 10 ? '0' + num : num;
  },

  niceTime:function(seconds) {
    var parts = this.getTimeParts(seconds);
    parts.hours = parts.hours > 0 ? parts.hours + ':' : '';
    parts.minutes = parts.minutes > 0 ? this.padNumber(parts.minutes) + ':' : '';
    parts.seconds = this.padNumber(parts.seconds);
    return parts.hours + parts.minutes + parts.seconds;
  }

};