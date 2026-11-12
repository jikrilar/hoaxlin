import { Chart, LineController, LineElement, PointElement, CategoryScale, LinearScale, Legend, Tooltip, DoughnutController, ArcElement } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, CategoryScale, LinearScale, Legend, Tooltip, DoughnutController, ArcElement);

export default Chart;
