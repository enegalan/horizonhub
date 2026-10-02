import axios from 'axios';
import * as Turbo from '@hotwired/turbo';
import * as echarts from 'echarts';
import moment from 'moment';
import { httpDelete, httpGet, httpPost } from './lib/http';
import { isHotReloadEnabled } from './lib/sse';
import { registerDocumentDelegate } from './lib/dom';
import { parseFailedAtRange } from './lib/parse';
import { setLoading } from './components/loading-button';
import { applyTheme, cycleTheme, getTheme } from './components/theme';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Turbo = Turbo;
window.echarts = echarts;

if (!window.horizon) window.horizon = {};
if (!window.horizon.http) window.horizon.http = {};
window.horizon.http.get = httpGet;
window.horizon.http.post = httpPost;
window.horizon.http.delete = httpDelete;

window.horizon.registerDocumentDelegate = registerDocumentDelegate;
window.horizon.parseFailedAtRange = parseFailedAtRange;
window.horizon.setLoading = setLoading;
window.horizon.isHotReloadEnabled = isHotReloadEnabled;

if (!window.horizon.theme) window.horizon.theme = {};
window.horizon.theme.get = getTheme;
window.horizon.theme.cycle = cycleTheme;
window.horizon.theme.apply = applyTheme;

if (!window.moment) window.moment = moment;
