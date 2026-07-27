
const DATA_COUNT = 7;
const NUMBER_CFG = {count: DATA_COUNT, min: -100, max: 100};

const labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const data = {
  labels: labels,
  datasets: [
    {
      label: 'Dataset 1',
      data: [[300,450]],
      borderColor: Utils.CHART_COLORS.red,
      backgroundColor: Utils.transparentize(Utils.CHART_COLORS.red, 0.5),
    },
    {
      label: 'Dataset 2',
      data: [],
      borderColor: Utils.CHART_COLORS.blue,
      backgroundColor: Utils.transparentize(Utils.CHART_COLORS.blue, 0.5),
    }
  ],
};


const config = {
  type: 'bar',
  data: data,
  options: {
    indexAxis: 'y',
    // Elements options apply to all of the options unless overridden in a dataset
    // In this case, we are setting the border of each horizontal bar to be 2px wide
    elements: {
      bar: {
        borderWidth: 2,
      }
    },
    responsive: true,
    plugins: {
      legend: {
        position: 'right',
      },
      title: {
        display: true,
        text: 'Chart.js Horizontal Bar Chart'
      },
      tooltip: {
        callbacks: {
          label(context) {
            let from_hours = Math.floor(context.raw[0] / 60);
            let from_minutes = Math.floor(context.raw[0] - from_hours * 60);
      
            let to_hours = Math.floor(context.raw[1] / 60);
            let to_minutes = Math.floor(context.raw[1] - to_hours * 60);
            
            return `${from_hours % 12 || 12}:${from_minutes.toString().padStart(2, 0)} ${from_hours < 12 || from_hours == 24 ? 'AM' : 'PM'} - ${to_hours % 12 || 12}:${to_minutes} ${to_hours < 12 || to_hours == 24 ? 'AM' : 'PM'}`
          }
        }
      }
    },
    scales: {
      x: {
        min: 0,
        max: 60 * 24,
        ticks: {
          callback(value, index, ticks) {
            let hours = Math.floor(value / 60);
            let minutes = Math.floor(value - hours * 60);
            return `${hours % 12 || 12} ${hours < 12 || hours == 24 ? 'AM' : 'PM'}`
          },
          stepSize: 180
        }
      }
    }
  },
};