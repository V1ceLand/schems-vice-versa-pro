/*
 * Демонстрация приложения «Пары» прямо на странице.
 *
 * Всё считается в браузере: расписание раскладывается портом ScheduleEngine
 * (engine.js), фаза дня зависит от положения «машины времени», данные лежат
 * в data.js. Сервера здесь нет и сеть не нужна.
 */

import { GROUP, HOMEWORK, MEMBERS, MESSAGES, PERMISSIONS, PLANS, SLOTS, THREADS } from './data.js';
import {
  DAY_NAMES, DAY_SHORT, addDays, isoDay, minutesToTime, mondayOf, overrideDate,
  plural, remaining, resolveDay, sameDay, startOfDay, status, weekType,
} from './engine.js';
import { icon } from './icons.js';

/* ------------------------------------------------------------------ утилиты */

const $ = (sel, root = document) => root.querySelector(sel);
const esc = (value) => String(value).replace(/[&<>"']/g, (c) =>
  ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const MONTHS = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
  'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];

const longDate = (d) => `${d.getDate()} ${MONTHS[d.getMonth()]}`;

/** Демонстрационное «сегодня»: воскресенье сдвигаем на понедельник — в выходной показывать нечего. */
function demoToday() {
  const real = startOfDay(new Date());
  return isoDay(real) === 7 ? addDays(real, 1) : real;
}

/* -------------------------------------------------------------------- тема */

const themeKey = 'pairs-demo-theme';

function applyTheme(theme) {
  document.documentElement.dataset.theme = theme;
  const btn = $('#theme-toggle');
  if (btn) {
    btn.textContent = theme === 'dark' ? '☀' : '☾';
    btn.setAttribute('aria-label', theme === 'dark' ? 'Светлая тема' : 'Тёмная тема');
  }
  document.querySelectorAll('[data-theme-seg] button').forEach((b) => {
    b.setAttribute('aria-pressed', String(b.dataset.themeValue === theme));
  });
}

function initTheme() {
  const saved = localStorage.getItem(themeKey);
  const system = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  applyTheme(saved || system);
  $('#theme-toggle')?.addEventListener('click', () => {
    const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem(themeKey, next);
    applyTheme(next);
  });
}

/* ------------------------------------------------------------------ состояние */

const TODAY = demoToday();

const state = {
  tab: 'TODAY',
  route: null,
  selected: TODAY,
  minutes: 0,          // время «машины времени» в минутах от полуночи
  weekView: weekType(TODAY),
  done: new Set(HOMEWORK.filter((h) => h.done).map((h) => h.id)),
  showDone: false,
  playing: false,
  notif: false,
  settings: { notifications: true, dynamicColor: true, p2p: false, compress: false, morning: 420 },
};

/** Время по умолчанию: реальное, если оно попадает в учебный день, иначе 10:30. */
(function initClock() {
  const now = new Date();
  const minutes = now.getHours() * 60 + now.getMinutes();
  state.minutes = minutes >= 7 * 60 && minutes <= 20 * 60 + 40 ? minutes : 10 * 60 + 30;
})();

const simulatedNow = () => {
  const base = state.selected;
  return new Date(base.getFullYear(), base.getMonth(), base.getDate(),
    Math.floor(state.minutes / 60), state.minutes % 60);
};

/** Момент «сейчас» относительно выбранной даты: прошлое/будущее считаем целиком. */
function nowFor(date) {
  if (sameDay(date, state.selected)) return simulatedNow();
  return date < state.selected ? new Date(date.getFullYear(), date.getMonth(), date.getDate(), 23, 59)
    : new Date(date.getFullYear(), date.getMonth(), date.getDate(), 0, 0);
}

/* ------------------------------------------------------------------- экраны */

function appbar({ title, subtitle, back, action, badge }) {
  return `<div class="appbar"><div class="appbar__row">
    ${back ? `<button class="icon-btn" data-act="back" aria-label="Назад">${icon('back')}</button>` : ''}
    <div class="appbar__title">${esc(title)}${subtitle ? `<div style="font-size:12px;font-weight:400;opacity:.75;line-height:1.2">${esc(subtitle)}</div>` : ''}</div>
    ${action || ''}
    ${badge || ''}
  </div></div>`;
}

const PHASE_CLASS = {
  LESSON: 'daycard--lesson', MIDDLE_BREAK: 'daycard--five', BETWEEN: 'daycard--between',
  BEFORE_FIRST: 'daycard--before', FINISHED: 'daycard--done', NO_CLASSES: 'daycard--done',
};

const PHASE_LABEL = {
  LESSON: 'Идёт пара', MIDDLE_BREAK: 'Пятиминутка', BETWEEN: 'Перерыв',
  BEFORE_FIRST: 'До первой пары', FINISHED: 'День закончился', NO_CLASSES: 'Выходной',
};

const PHASE_ICON = {
  LESSON: 'book', MIDDLE_BREAK: 'clock', BETWEEN: 'clock',
  BEFORE_FIRST: 'clock', FINISHED: 'check', NO_CLASSES: 'today',
};

function weekStrip() {
  const monday = mondayOf(state.selected);
  const cells = Array.from({ length: 7 }, (_, i) => {
    const date = addDays(monday, i);
    const has = resolveDay(date, TODAY).length > 0;
    const isSelected = sameDay(date, state.selected);
    const isToday = sameDay(date, TODAY);
    const cls = ['weekstrip__day'];
    if (isToday && !isSelected) cls.push('weekstrip__day--today');
    if (isSelected) cls.push('weekstrip__day--selected');
    return `<button class="${cls.join(' ')}" data-act="pick-day" data-date="${date.toISOString()}"
      aria-label="${DAY_NAMES[i]}, ${longDate(date)}${has ? ', есть занятия' : ', занятий нет'}"
      aria-pressed="${isSelected}">
      <span class="weekstrip__dow">${DAY_SHORT[i]}</span>
      <span class="weekstrip__num">${date.getDate()}</span>
      <span class="weekstrip__dot${has ? '' : ' weekstrip__dot--empty'}"></span>
    </button>`;
  }).join('');
  return `<div class="weekstrip">${cells}</div>`;
}

function dayCard(day, lessons) {
  const now = nowFor(state.selected);
  const st = status(now, lessons, state.settings.morning);
  const pct = Math.round((st.progress || 0) * 100);
  const next = st.next && st.phase !== 'LESSON' ? null : st.next;
  return `<div class="daycard ${PHASE_CLASS[st.phase]}">
    <div class="daycard__head">${icon(PHASE_ICON[st.phase], 16)}<span>${PHASE_LABEL[st.phase]}</span></div>
    <div class="daycard__title">${esc(st.title)}</div>
    ${st.subtitle ? `<div class="daycard__sub">${esc(st.subtitle)}</div>` : ''}
    ${st.phase !== 'NO_CLASSES' && st.phase !== 'FINISHED'
      ? `<div class="daycard__bar"><span style="width:${pct}%"></span></div>` : ''}
    ${next ? `<div class="daycard__next">Дальше: <b>${esc(next.subject)}</b> · ${minutesToTime(next.slot.start)}</div>` : ''}
  </div>`;
}

function lessonRow(lesson, now, current, neutral = false) {
  const start = lesson.slot.start;
  const end = lesson.slot.end;
  const nowMin = now.getHours() * 60 + now.getMinutes();
  const isCurrent = current && current.pair === lesson.pair;
  const isPast = !isCurrent && nowMin >= end && sameDay(lesson.date, state.selected);
  const isNext = !isCurrent && !isPast && nowMin < start;
  const cls = ['lesson'];
  if (neutral) { /* постоянное расписание рисуется без состояний */ }
  else if (isCurrent) cls.push('lesson--current');
  else if (isPast) cls.push('lesson--past');
  else if (isNext) cls.push('lesson--next');

  const tags = [];
  if (lesson.oneOff) tags.push('<span class="tag tag--oneoff">только сегодня</span>');
  if (lesson.five === 'MIDDLE') tags.push('<span class="tag tag--five">пятиминутка</span>');

  return `<div class="${cls.join(' ')}">
    <div class="lesson__time">${minutesToTime(start)}<br>${minutesToTime(end)}</div>
    <div class="lesson__rail"></div>
    <div class="lesson__main">
      <div class="lesson__subject">${esc(lesson.subject)}</div>
      <div class="lesson__meta">
        ${lesson.teacher ? `<span>${esc(lesson.teacher)}</span>` : ''}
        ${lesson.room ? `<span>· ауд. ${esc(lesson.room)}</span>` : ''}
      </div>
      ${tags.length ? `<div class="lesson__meta" style="margin-top:6px">${tags.join('')}</div>` : ''}
    </div>
    <div class="lesson__num">${lesson.pair}</div>
  </div>`;
}

function screenToday() {
  const lessons = resolveDay(state.selected, TODAY);
  const now = nowFor(state.selected);
  const st = status(now, lessons, state.settings.morning);
  const type = weekType(state.selected) === 'EVEN' ? 'чётная неделя' : 'нечётная неделя';
  const title = sameDay(state.selected, TODAY) ? 'Сегодня' : DAY_NAMES[isoDay(state.selected) - 1];
  const isOverride = sameDay(state.selected, overrideDate(TODAY));

  return appbar({
    title,
    subtitle: `${longDate(state.selected)} · ${type}`,
    action: `<button class="icon-btn" data-act="notify" aria-label="Показать уведомление">${icon('bell')}</button>`,
  })
    + weekStrip()
    + dayCard(state.selected, lessons)
    + (lessons.length
      ? `<div class="section-label">${plural(lessons.length, 'занятие', 'занятия', 'занятий')}${isOverride ? ' · замена на день' : ''}</div>
         <div class="lessons">${lessons.map((l) => lessonRow(l, now, st.current)).join('')}</div>`
      : `<div class="empty">В этот день занятий нет.<br>Полоса недели показывает точкой те дни, где они есть.</div>`);
}

function screenWeek() {
  const monday = mondayOf(state.selected);

  // Вкладка «Неделя» показывает постоянное расписание выбранного типа недели,
  // а не конкретные даты: так его и правят.
  const byDay = Array.from({ length: 6 }, (_, i) => {
    const probe = addDays(monday, i);
    const shifted = weekType(probe) === state.weekView ? probe : addDays(probe, 7);
    return { day: i + 1, date: shifted, lessons: resolveDay(shifted, null) };
  });

  const body = byDay.map(({ day, lessons }) => {
    if (!lessons.length) return '';
    return `<div class="daygroup">
      <div class="daygroup__head"><span>${DAY_NAMES[day - 1]}</span>
        <span class="daygroup__count">${plural(lessons.length, 'занятие', 'занятия', 'занятий')}</span></div>
      <div class="lessons" style="padding:0">
        ${lessons.map((l) => lessonRow(l, new Date(2000, 0, 1), null, true)).join('')}
      </div>
    </div>`;
  }).join('') || '<div class="empty">На эту неделю занятий нет.</div>';

  const current = weekType(TODAY) === 'EVEN' ? 'чётная' : 'нечётная';
  return appbar({ title: 'Неделя', subtitle: `постоянное расписание · сейчас ${current}` })
    + `<div class="weekpicker">
        <button class="chip ${state.weekView === 'EVEN' ? 'chip--on' : ''}" data-act="week" data-week="EVEN">Чётная</button>
        <button class="chip ${state.weekView === 'ODD' ? 'chip--on' : ''}" data-act="week" data-week="ODD">Нечётная</button>
      </div>` + body;
}

function dueLabel(hw) {
  const due = addDays(TODAY, hw.dueOffset);
  const when = hw.dueOffset === 0 ? 'сегодня'
    : hw.dueOffset === 1 ? 'завтра'
    : hw.dueOffset === -1 ? 'вчера'
    : longDate(due);
  return `${when}, ${hw.dueTime}`;
}

function hwCard(hw) {
  const done = state.done.has(hw.id);
  const overdue = !done && hw.dueOffset < 0;
  const cls = ['hw__card'];
  if (done) cls.push('hw__card--done');
  else if (overdue) cls.push('hw__card--overdue');

  const tags = [`<span class="tag">${esc(dueLabel(hw))}</span>`];
  if (hw.reminder) tags.push(`<span class="tag">напомнить за ${hw.reminder >= 1440 ? '1 день' : `${hw.reminder / 60} ч`}</span>`);
  if (hw.scope === 'personal') tags.push('<span class="tag">только у меня</span>');
  if (hw.photo) tags.push('<span class="tag tag--five">фото</span>');

  return `<button class="${cls.join(' ')}" data-act="toggle-hw" data-id="${hw.id}"
      aria-pressed="${done}">
    <span class="hw__check">${done ? `<span>${icon('check', 13)}</span>` : ''}</span>
    <span>
      <span class="hw__subject">${esc(hw.subject)}</span>
      <span class="hw__title" style="display:block">${esc(hw.title)}</span>
      ${hw.description ? `<span class="hw__desc" style="display:block">${esc(hw.description)}</span>` : ''}
      <span class="hw__meta">${tags.join('')}</span>
    </span>
  </button>`;
}

function screenHomework() {
  const items = HOMEWORK.map((h) => ({ ...h, done: state.done.has(h.id) }));
  const overdue = items.filter((h) => !h.done && h.dueOffset < 0);
  const actual = items.filter((h) => !h.done && h.dueOffset >= 0).sort((a, b) => a.dueOffset - b.dueOffset);
  const done = items.filter((h) => h.done);

  const block = (label, list, extra = '') => list.length
    ? `<div class="section-label">${label}${extra}</div><div class="hw">${list.map(hwCard).join('')}</div>` : '';

  return appbar({
    title: 'Задания',
    subtitle: `${plural(actual.length + overdue.length, 'активное', 'активных', 'активных')} · синхронизировано`,
    action: `<button class="icon-btn" aria-label="Добавить задание">${icon('plus')}</button>`,
  })
    + block('Просрочено', overdue)
    + block('Актуальные', actual)
    + (done.length ? `<button class="hw__group-head" data-act="toggle-done" aria-expanded="${state.showDone}">
        ${icon('chevron', 16)} <span>Выполнено</span><span>${done.length}</span>
      </button>${state.showDone ? `<div class="hw">${done.map(hwCard).join('')}</div>` : ''}` : '')
    + (overdue.length + actual.length + done.length === 0 ? '<div class="empty">Заданий нет.</div>' : '');
}

const ROWS_MORE = [
  { route: 'PROFILE', icon: 'person', title: 'Профиль', sub: 'Имя, аватар, выход' },
  { route: 'GROUP', icon: 'group', title: 'Группа', sub: 'Участники и роли' },
  { route: 'FORUM', icon: 'forum', title: 'Форум', sub: 'Темы по предметам и конспектам' },
  { route: 'CHAT', icon: 'chat', title: 'Чат группы', sub: 'Сообщения, фото, файлы, голосовые' },
  { route: 'SETTINGS', icon: 'settings', title: 'Настройки', sub: 'Расписание и звонки, данные и хранилище' },
  { route: 'UPDATE', icon: 'update', title: 'Обновление', sub: 'Доступна версия 4.8', badge: true },
];

function screenMore() {
  return appbar({ title: 'Ещё' })
    + `<div class="list">${ROWS_MORE.map((r) => `
      <button class="row" data-act="route" data-route="${r.route}">
        <span class="row__icon">${icon(r.icon)}</span>
        <span><span class="row__title">${esc(r.title)}</span><span class="row__sub" style="display:block">${esc(r.sub)}</span></span>
        <span class="row__trail">${r.badge ? '<span class="badge-dot"></span>' : icon('chevron', 18)}</span>
      </button>`).join('')}</div>
    <div class="section-label">Приложение</div>
    <div class="list">
      <div class="row row--static">
        <span class="row__icon">${icon('cloud')}</span>
        <span><span class="row__title">Синхронизация</span>
        <span class="row__sub" style="display:block">Последняя — минуту назад, фоном раз в 15 минут</span></span>
      </div>
      <div class="row row--static">
        <span class="row__icon">${icon('telegram')}</span>
        <span><span class="row__title">Telegram-бот</span>
        <span class="row__sub" style="display:block">Расписание и задания в чате бота</span></span>
      </div>
    </div>`;
}

function screenProfile() {
  return appbar({ title: 'Профиль', back: true })
    + `<div class="list">
      <div class="row row--static">
        <span class="row__icon" style="background:var(--primary-container);color:var(--on-primary-container)">В</span>
        <span><span class="row__title">Вы</span><span class="row__sub" style="display:block">вход через сайт «Пары» · Google</span></span>
      </div>
    </div>
    <div class="section-label">Группа</div>
    <div class="list">
      <div class="row row--static"><span class="row__icon">${icon('group')}</span>
        <span><span class="row__title">${esc(GROUP.name)}</span>
        <span class="row__sub" style="display:block">роль: Ученик · код ${esc(GROUP.inviteCode)}</span></span></div>
      <div class="row row--static"><span class="row__icon">${icon('cloud')}</span>
        <span><span class="row__title">Хранилище группы</span>
        <span class="row__sub" style="display:block">${(GROUP.storageUsedMb / 1024).toFixed(1)} ГБ из ${(GROUP.storageLimitMb / 1024).toFixed(0)} ГБ · ${esc(GROUP.plan)}</span></span></div>
    </div>
    <div class="section-label">Устройства</div>
    <div class="list">
      <div class="row row--static"><span class="row__icon">${icon('bell')}</span>
        <span><span class="row__title">Push-уведомления</span>
        <span class="row__sub" style="display:block">Pixel 7a · подключено</span></span></div>
    </div>`;
}

function screenGroup() {
  const used = Math.round((GROUP.storageUsedMb / GROUP.storageLimitMb) * 100);
  return appbar({ title: 'Группа', back: true, subtitle: GROUP.name })
    + `<div class="daycard" style="background:var(--surface-container)">
      <div class="daycard__head">${icon('cloud', 16)}<span>Хранилище</span></div>
      <div class="daycard__sub">${(GROUP.storageUsedMb / 1024).toFixed(1)} ГБ из ${(GROUP.storageLimitMb / 1024).toFixed(0)} ГБ · тариф ${esc(GROUP.plan)}</div>
      <div class="daycard__bar"><span style="width:${used}%"></span></div>
    </div>
    <div class="section-label">Участники · ${GROUP.members}</div>
    <div class="list">${MEMBERS.map((m) => `
      <div class="row row--static">
        <span class="row__icon">${esc(m.initials)}</span>
        <span><span class="row__title">${esc(m.name)}</span>
        ${m.note ? `<span class="row__sub" style="display:block">${esc(m.note)}</span>` : ''}</span>
        <span class="pill">${esc(m.role)}</span>
      </div>`).join('')}</div>
    <div class="section-label">Приглашение</div>
    <div class="list"><div class="row row--static">
      <span class="row__icon">${icon('plus')}</span>
      <span><span class="row__title">Код ${esc(GROUP.inviteCode)}</span>
      <span class="row__sub" style="display:block">Действует 7 дней, до 30 вступлений</span></span>
    </div></div>`;
}

function screenForum() {
  return appbar({ title: 'Форум', back: true, action: `<button class="icon-btn" aria-label="Новая тема">${icon('plus')}</button>` })
    + `<div class="list">${THREADS.map((t) => `
      <button class="row" data-act="route" data-route="CHAT">
        <span class="row__icon" style="${t.kind === 'chat' ? 'background:var(--primary-container);color:var(--on-primary-container)' : ''}">${icon(t.kind === 'chat' ? 'chat' : 'forum')}</span>
        <span><span class="row__title">${esc(t.title)}</span>
        <span class="row__sub" style="display:block">${esc(t.last)}</span></span>
        <span class="row__trail">${t.unread ? `<span class="pill">${t.unread}</span>` : icon('chevron', 18)}</span>
      </button>`).join('')}</div>`;
}

function bars(n, seed) {
  return Array.from({ length: n }, (_, i) => {
    const h = 4 + ((Math.sin(i * seed) + 1) / 2) * 18;
    return `<i style="height:${h.toFixed(0)}px"></i>`;
  }).join('');
}

function screenChat() {
  const body = MESSAGES.map((m) => {
    const parts = [];
    if (!m.mine) parts.push(`<div class="msg__author">${esc(m.author)}</div>`);
    if (m.pinned) parts.push('<div class="msg__foot" style="margin:0 0 4px">📌 закреплено</div>');
    if (m.text) parts.push(esc(m.text));
    if (m.voice) {
      parts.push(`<div class="msg__voice">${icon('mic', 18)}
        <span class="msg__wave">${bars(26, 1.1)}</span>
        <span style="font-size:11px">0:${String(m.voice.seconds).padStart(2, '0')}</span></div>`);
    }
    if (m.attachment) {
      parts.push(`<div class="msg__attach"><span class="msg__thumb">${icon('image', 18)}</span>
        <span><span style="display:block;font-size:12px;font-weight:500">${esc(m.attachment.name)}</span>
        <span style="font-size:11px;opacity:.75">${esc(m.attachment.size)}</span></span></div>`);
    }
    const foot = [`<span>${esc(m.at)}</span>`];
    if (m.edited) foot.push('<span>изменено</span>');
    if (m.read) foot.push(`<span>${icon('check', 12)} ${m.read}</span>`);
    parts.push(`<div class="msg__foot">${foot.join('')}</div>`);
    if (m.reactions) {
      parts.push(`<div class="msg__reactions">${m.reactions.map((r) =>
        `<span class="reaction">${r.emoji} ${r.count}</span>`).join('')}</div>`);
    }

    return `<div class="msg ${m.mine ? 'msg--mine' : ''}">
      ${m.mine ? '' : `<div class="msg__avatar">${esc(m.initials)}</div>`}
      <div class="msg__bubble">${parts.join('')}</div>
    </div>`;
  }).join('');

  return appbar({ title: 'Общий чат', back: true, subtitle: `${GROUP.members} участника · 3 онлайн` })
    + `<div class="chat">${body}</div>
    <div class="composer">
      <button class="icon-btn" aria-label="Вложение">${icon('plus')}</button>
      <span class="composer__field">Сообщение…</span>
      <button class="icon-btn" aria-label="Голосовое">${icon('mic')}</button>
      <button class="icon-btn" aria-label="Отправить">${icon('send')}</button>
    </div>`;
}

function switchRow(label, sub, key) {
  const on = state.settings[key];
  return `<div class="switch-row">
    <span><span class="row__title">${esc(label)}</span>
    <span class="row__sub" style="display:block">${esc(sub)}</span></span>
    <button class="switch" role="switch" aria-checked="${on}" aria-label="${esc(label)}"
      data-act="switch" data-key="${key}"></button>
  </div>`;
}

function screenSettings() {
  const theme = document.documentElement.dataset.theme;
  return appbar({ title: 'Настройки', back: true })
    + `<div class="section-label">Оформление</div>
    <div class="list">
      <div class="switch-row switch-row--stack">
        <span><span class="row__title">Тема</span>
        <span class="row__sub" style="display:block">Как в системе, светлая или тёмная</span></span>
        <span class="seg" data-theme-seg>
          <button data-act="theme" data-theme-value="light" aria-pressed="${theme === 'light'}">Светлая</button>
          <button data-act="theme" data-theme-value="dark" aria-pressed="${theme === 'dark'}">Тёмная</button>
        </span>
      </div>
      ${switchRow('Цвета из обоев', 'Динамическая палитра Android 12+', 'dynamicColor')}
    </div>
    <div class="section-label">Уведомления</div>
    <div class="list">
      ${switchRow('Тихое уведомление', 'Фаза дня и «осталось N минут» в шторке', 'notifications')}
      <div class="row row--static">
        <span class="row__icon">${icon('clock')}</span>
        <span><span class="row__title">Показывать с</span>
        <span class="row__sub" style="display:block">${minutesToTime(state.settings.morning)} — до этого времени уведомления нет</span></span>
      </div>
    </div>
    <div class="section-label">Расписание и звонки</div>
    <div class="list">
      <div class="row row--static"><span class="row__icon">${icon('today')}</span>
        <span><span class="row__title">Отсчёт недель</span>
        <span class="row__sub" style="display:block">1 сентября 2026 — чётная</span></span></div>
      <div class="row row--static"><span class="row__icon">${icon('clock')}</span>
        <span><span class="row__title">Звонки</span>
        <span class="row__sub" style="display:block">${SLOTS.length} пар: с ${minutesToTime(SLOTS[0].start)} до ${minutesToTime(SLOTS[SLOTS.length - 1].end)}</span></span></div>
      <div class="row row--static"><span class="row__icon">${icon('download')}</span>
        <span><span class="row__title">Файл расписания</span>
        <span class="row__sub" style="display:block">Импорт и экспорт JSON, объединение или замена</span></span></div>
    </div>
    <div class="section-label">Данные и хранилище</div>
    <div class="list">
      ${switchRow('Сжимать отправленные фото', 'Оригинал уходит сразу, сжатая версия подменяет его позже', 'compress')}
      ${switchRow('Обмен по Wi-Fi (P2P)', 'Вложения качаются у соседа по группе, а не с сервера', 'p2p')}
      <div class="row row--static"><span class="row__icon">${icon('cloud')}</span>
        <span><span class="row__title">Локальный кэш вложений</span>
        <span class="row__sub" style="display:block">Занято 34 МБ из 100 МБ</span></span></div>
    </div>`;
}

function screenUpdate() {
  return appbar({ title: 'Обновление', back: true })
    + `<div class="daycard" style="background:var(--primary-container);color:var(--on-primary-container)">
      <div class="daycard__head">${icon('update', 16)}<span>Доступна версия 4.8</span></div>
      <div class="daycard__title" style="font-size:16px">Установлена 4.7 (versionCode 9)</div>
      <div class="daycard__sub">APK 18,4 МБ · SHA-256 проверяется после загрузки</div>
      <div class="daycard__bar"><span style="width:64%"></span></div>
      <div class="daycard__sub">Загружено 11,8 МБ из 18,4 МБ</div>
    </div>
    <div class="section-label">Что нового</div>
    <div class="list">
      ${['Форум: темы по предметам и конспектам',
        'Голосовые сообщения и реакции в чате',
        'История правок сообщений и восстановление',
        'Обмен вложениями по Wi-Fi между устройствами группы',
        'Роли с точными правами вместо одного «может управлять»']
        .map((line) => `<div class="row row--static">
          <span class="row__icon" style="background:var(--success-container);color:var(--on-success-container)">${icon('check', 18)}</span>
          <span><span class="row__title" style="font-weight:400">${esc(line)}</span></span></div>`).join('')}
    </div>
    <div class="list"><div class="row row--static">
      <span class="row__icon">${icon('download')}</span>
      <span><span class="row__title">Установка через PackageInstaller</span>
      <span class="row__sub" style="display:block">Окно подтверждения показывает система, FileProvider не нужен</span></span>
    </div></div>`;
}

const SCREENS = {
  TODAY: screenToday, WEEK: screenWeek, HOMEWORK: screenHomework, MORE: screenMore,
  PROFILE: screenProfile, GROUP: screenGroup, FORUM: screenForum, CHAT: screenChat,
  SETTINGS: screenSettings, UPDATE: screenUpdate,
};

const TABS = [
  { id: 'TODAY', label: 'Сегодня', icon: 'today' },
  { id: 'WEEK', label: 'Неделя', icon: 'week' },
  { id: 'HOMEWORK', label: 'Задания', icon: 'assignment' },
  { id: 'MORE', label: 'Ещё', icon: 'more' },
];

function navbar() {
  return `<nav class="navbar" aria-label="Разделы приложения">${TABS.map((t) => {
    const on = state.route === null && state.tab === t.id;
    const badge = t.id === 'MORE';
    return `<button class="navbar__item ${on ? 'navbar__item--on' : ''}" data-act="tab" data-tab="${t.id}"
      aria-current="${on ? 'page' : 'false'}">
      <span class="navbar__icon">${icon(t.icon)}${badge ? '<span class="navbar__badge"></span>' : ''}</span>
      <span class="navbar__label">${t.label}</span>
    </button>`;
  }).join('')}</nav>`;
}

/* --------------------------------------------------------------- отрисовка */

function notification() {
  const lessons = resolveDay(state.selected, TODAY);
  const st = status(nowFor(state.selected), lessons, state.settings.morning);
  return `<div class="notif ${state.notif ? 'notif--on' : ''}" role="status">
    <span class="notif__icon">П</span>
    <span>
      <span class="notif__app">Пары · тихое уведомление</span>
      <span class="notif__title" style="display:block">${st.phase === 'LESSON'
        ? `${esc(PHASE_LABEL[st.phase])} — ${esc(st.title)}` : esc(st.title)}</span>
      <span class="notif__text" style="display:block">${esc(st.subtitle || 'Обновляется по звонку')}</span>
    </span>
  </div>`;
}

function render() {
  const screen = SCREENS[state.route || state.tab] || screenToday;
  const clock = minutesToTime(state.minutes);
  $('#phone-screen').innerHTML = `
    <div class="phone__status"><span>${clock}</span>
      <span class="phone__status-icons"><i></i><i></i><i></i></span></div>
    ${notification()}
    <div class="phone__body">${screen()}</div>
    ${navbar()}`;
  syncPanel();
}

/* ----------------------------------------------------------- взаимодействие */

function handle(event) {
  const el = event.target.closest('[data-act]');
  if (!el) return;
  const act = el.dataset.act;

  if (act === 'tab') { state.route = null; state.tab = el.dataset.tab; }
  else if (act === 'route') { state.route = el.dataset.route; }
  else if (act === 'back') { state.route = null; }
  else if (act === 'pick-day') {
    state.selected = new Date(el.dataset.date);
    state.weekView = weekType(state.selected);
  } else if (act === 'week') { state.weekView = el.dataset.week; }
  else if (act === 'toggle-hw') {
    const id = el.dataset.id;
    state.done.has(id) ? state.done.delete(id) : state.done.add(id);
  } else if (act === 'toggle-done') { state.showDone = !state.showDone; }
  else if (act === 'switch') {
    const key = el.dataset.key;
    state.settings[key] = !state.settings[key];
  } else if (act === 'theme') {
    localStorage.setItem(themeKey, el.dataset.themeValue);
    applyTheme(el.dataset.themeValue);
  } else if (act === 'notify') {
    state.notif = !state.notif;
    if (state.notif) setTimeout(() => { state.notif = false; render(); }, 4200);
  } else return;

  render();
}

/* --------------------------------------------- панель управления рядом с телефоном */

/** Первое время суток, дающее нужную фазу на выбранной дате. */
function findPhase(phase) {
  const lessons = resolveDay(state.selected, TODAY);
  for (let m = 6 * 60; m <= 22 * 60; m += 1) {
    const probe = new Date(state.selected.getFullYear(), state.selected.getMonth(),
      state.selected.getDate(), Math.floor(m / 60), m % 60);
    if (status(probe, lessons, state.settings.morning).phase === phase) return m + 2;
  }
  return null;
}

function syncPanel() {
  const lessons = resolveDay(state.selected, TODAY);
  const st = status(nowFor(state.selected), lessons, state.settings.morning);
  const clock = $('#clock-value');
  if (clock) clock.textContent = minutesToTime(state.minutes);
  const range = $('#clock-range');
  if (range && Number(range.value) !== state.minutes) range.value = String(state.minutes);
  const hint = $('#phase-hint');
  if (hint) {
    hint.innerHTML = `<strong>${esc(PHASE_LABEL[st.phase])}</strong> · ${esc(st.subtitle || st.title)}`;
  }
  document.querySelectorAll('[data-scenario]').forEach((btn) => {
    btn.disabled = findPhase(btn.dataset.scenario) === null;
    btn.style.opacity = btn.disabled ? '.45' : '';
  });
}

let timer = null;

function play(on) {
  state.playing = on;
  const btn = $('#play');
  if (btn) btn.textContent = on ? '⏸ Пауза' : '▶ Проиграть день';
  clearInterval(timer);
  if (!on) return;
  timer = setInterval(() => {
    state.minutes += 3;
    if (state.minutes > 21 * 60) { state.minutes = 7 * 60; }
    render();
  }, 90);
}

function initDemo() {
  $('#phone-screen').addEventListener('click', handle);

  $('#clock-range').addEventListener('input', (e) => {
    state.minutes = Number(e.target.value);
    if (state.playing) play(false);
    render();
  });

  $('#play').addEventListener('click', () => play(!state.playing));

  $('#reset-day').addEventListener('click', () => {
    state.selected = TODAY;
    state.weekView = weekType(TODAY);
    state.tab = 'TODAY';
    state.route = null;
    render();
  });

  document.querySelectorAll('[data-scenario]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const minutes = findPhase(btn.dataset.scenario);
      if (minutes === null) return;
      if (state.playing) play(false);
      state.minutes = Math.min(minutes, 23 * 60 + 59);
      state.tab = 'TODAY';
      state.route = null;
      render();
    });
  });

  document.querySelectorAll('[data-goto]').forEach((btn) => {
    btn.addEventListener('click', () => {
      state.route = null;
      const target = btn.dataset.goto;
      if (TABS.some((t) => t.id === target)) state.tab = target;
      else { state.tab = 'MORE'; state.route = target; }
      render();
      $('#demo').scrollIntoView({ block: 'center' });
    });
  });

  render();
}

/* ------------------------------------------------- статические блоки страницы */

function renderPermissions() {
  const host = $('#perm-table');
  if (!host) return;
  host.innerHTML = `<table>
    <caption class="visually-hidden">Права ролей по умолчанию</caption>
    <thead><tr><th>Право</th>${PERMISSIONS.columns.map((c) => `<th>${esc(c)}</th>`).join('')}</tr></thead>
    <tbody>${PERMISSIONS.rows.map((row) => `<tr>
      <td>${esc(row.name)} <code>${esc(row.key)}</code></td>
      ${row.bits.map((b) => `<td>${b ? '<span class="yes" title="есть">✓</span>' : '<span class="no" title="нет">—</span>'}</td>`).join('')}
    </tr>`).join('')}</tbody>
  </table>`;
}

function renderPlans() {
  const host = $('#plans-grid');
  if (!host) return;
  host.innerHTML = PLANS.map((p) => `<div class="plan ${p.highlight ? 'plan--hl' : ''}">
    ${p.highlight ? '<span class="plan__flag">Чаще всего берут</span>' : ''}
    <h3>${esc(p.name)}</h3>
    <div class="plan__price">${p.month ? `${p.month} ₽ <small>/ месяц</small>` : '0 ₽'}</div>
    ${p.year ? `<div class="plan__year">или ${p.year} ₽ в год · хранилище ${esc(p.storage)}</div>`
      : '<div class="plan__year">навсегда, без хранилища вложений</div>'}
    <p class="plan__note">${esc(p.note)}</p>
    <ul>${p.features.map((f) => `<li><span>${esc(f)}</span></li>`).join('')}</ul>
  </div>`).join('');
}

function renderBells() {
  const host = $('#bells');
  if (!host) return;
  host.innerHTML = SLOTS.map((s) => `<div class="api-row">
    <span class="method method--get">${s.number} пара</span>
    <span class="mono">${minutesToTime(s.start)} — ${minutesToTime(s.end)}
      <span style="opacity:.7">· ${s.five === 'MIDDLE' ? 'пятиминутка в середине'
        : s.five === 'EARLY_END' ? 'отпускают на 5 минут раньше' : 'без пятиминутки'}</span></span>
  </div>`).join('');
}

/* ---------------------------------------------------------------------- старт */

initTheme();
renderPermissions();
renderPlans();
renderBells();
initDemo();

// Год в подвале и «сегодня» в подписи демонстрации.
$('#year').textContent = String(new Date().getFullYear());
$('#demo-date').textContent = `${longDate(TODAY)}, ${DAY_NAMES[isoDay(TODAY) - 1].toLowerCase()}, `
  + `${weekType(TODAY) === 'EVEN' ? 'чётная' : 'нечётная'} неделя`;
const OVERRIDE_DAY = overrideDate(TODAY);
$('#override-date').textContent = `${longDate(OVERRIDE_DAY)}, ${DAY_NAMES[isoDay(OVERRIDE_DAY) - 1].toLowerCase()}`;
