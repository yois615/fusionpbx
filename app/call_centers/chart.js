const coverage_chart_labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const coverage_chart_options = {
  indexAxis: 'y',
  responsive: true,
  plugins: {
    legend: {
      position: 'bottom'
    },
    tooltip: {
      callbacks: {
        title([context]) {
          return context.dataset.label
        },
        label(context) {
          return `${coverage_chart_num_to_time(context.raw.x[0])} - ${coverage_chart_num_to_time(context.raw.x[1])} (Tier ${context.raw.tier})`
        },
      },
      animations: {
        numbers: false
      },
      position: 'middle',
      yAlign: 'top',
      xAlign: 'center',
      displayColors: false
    }
  },
  scales: {
    x: {
      min: 0,
      max: 60 * 24,
      ticks: {
        callback(value) {
          let hours = Math.floor(value / 60);
          return `${hours % 12 || 12} ${hours < 12 || hours == 24 ? 'AM' : 'PM'}`
        },
        stepSize: 180
      }
    }
  },
  maxBarThickness: 10
}

Chart.Tooltip.positioners.middle = function(elements, eventPosition) {
  return { x: elements[0] ? elements[0].element.x - elements[0].element.width / 2 : 0, y: elements[0]?.element.y ?? 0 }
}

function coverage_chart_time_to_num(timestr) {
  let hours = +timestr.substring(0, 2)
  let minutes = +timestr.substring(3)
  return hours * 60 + minutes
}

function coverage_chart_num_to_time(num) {
  let hours = Math.floor(num / 60);
  let minutes = Math.floor(num - hours * 60);
  return `${hours % 12 || 12}:${minutes.toString().padStart(2, '0')} ${hours < 12 || hours == 24 ? 'AM' : 'PM'}`
}
