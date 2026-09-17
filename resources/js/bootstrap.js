import axios from 'axios';
import * as Turbo from '@hotwired/turbo';
import * as echarts from 'echarts';
import moment from 'moment';
import { createHttpHelpers } from './lib/http';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Turbo = Turbo;
window.echarts = echarts;

if (!window.horizon) window.horizon = {};
window.horizon.http = createHttpHelpers();

if (!window.moment) window.moment = moment;
