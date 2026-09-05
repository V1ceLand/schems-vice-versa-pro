/*
 * Расчёт учебного дня для демонстрации: какая неделя, что стоит на дату
 * и в какой фазе день находится в выбранный момент.
 */

import { LESSONS, OVERRIDE, OVERRIDE_OFFSET_DAYS, SLOTS } from './data.js';

/** Опорная дата отсчёта чётности и тип недели на ней (настройка приложения). */
export const ANCHOR = { date: new Date(2026, 8, 1), weekType: 'EVEN' };

const DAY_MS = 86400000;

export const DAY_NAMES = ['Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];
export const DAY_SHORT = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

/** 1 — понедельник, 7 — воскресенье. */
export function isoDay(date) {
  return date.getDay() === 0 ? 7 : date.getDay();
}

export function startOfDay(date) {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

export function mondayOf(date) {
  return new Date(startOfDay(date).getTime() - (isoDay(date) - 1) * DAY_MS);
}

export function addDays(date, days) {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate() + days,
    date.getHours(), date.getMinutes(), date.getSeconds());
}

export function sameDay(a, b) {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

/** Чётность считается от опорной даты, обе даты приводятся к понедельнику. */
export function weekType(date) {
  const diff = Math.round((mondayOf(date) - mondayOf(ANCHOR.date)) / (7 * DAY_MS));
  const even = ((diff % 2) + 2) % 2 === 0;
  const other = ANCHOR.weekType === 'EVEN' ? 'ODD' : 'EVEN';
  return even ? ANCHOR.weekType : other;
}

export function minutesToTime(minutes) {
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

/**
 * Дата, на которую в демонстрации приходится замена на день: ближайший
 * учебный день после «сегодня». Просто «завтра» попадало бы на воскресенье,
 * и замена рисовала занятия там, где их нет.
 */
export function overrideDate(today) {
  const base = startOfDay(today);
  for (let i = OVERRIDE_OFFSET_DAYS; i < OVERRIDE_OFFSET_DAYS + 7; i += 1) {
    const date = addDays(base, i);
    if (regularLessons(date).length) return date;
  }
  return addDays(base, OVERRIDE_OFFSET_DAYS);
}

/** Постоянное расписание на дату, без учёта замен. */
function regularLessons(date) {
  const week = weekType(date);
  const day = isoDay(date);
  return LESSONS.filter((l) => l.weekType === week && l.day === day);
}

const slotByNumber = new Map(SLOTS.map((slot) => [slot.number, slot]));

function resolveFive(payloadFive, slot) {
  if (payloadFive && payloadFive !== 'INHERIT') return payloadFive;
  return slot.five || 'EARLY_END';
}

/**
 * Занятия на дату. Замена на день вытесняет постоянное расписание, а пометку
 * «только сегодня» получают ровно те пары, что отличаются от обычной недели.
 */
export function resolveDay(date, today) {
  const regular = regularLessons(date);

  let source;
  if (today && sameDay(date, overrideDate(today))) {
    const baseline = new Map(regular.map((l) => [l.pair, l]));
    source = OVERRIDE.map((payload) => {
      const base = baseline.get(payload.pair);
      const same = base && base.subject === payload.subject && base.teacher === payload.teacher && base.room === payload.room;
      return { ...payload, oneOff: !same };
    });
  } else {
    source = regular.map((l) => ({ ...l, oneOff: false }));
  }

  return source
    .map((payload) => {
      const slot = slotByNumber.get(payload.pair);
      if (!slot) return null;
      return {
        pair: payload.pair,
        subject: payload.subject,
        teacher: payload.teacher,
        room: payload.room,
        five: resolveFive(payload.five, slot),
        slot,
        oneOff: Boolean(payload.oneOff),
        date,
      };
    })
    .filter(Boolean)
    .sort((a, b) => a.slot.start - b.slot.start);
}

function at(date, minutes) {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate(), Math.floor(minutes / 60), minutes % 60);
}

function progress(now, from, to) {
  const total = Math.max(to - from, 1);
  return Math.min(Math.max(now - from, 0), total) / total;
}

/** «осталось 1 ч 20 мин» / «осталось 12 мин» / «меньше минуты». */
export function remaining(now, until) {
  const minutes = Math.max(Math.floor((until - now) / 60000), 0);
  if (minutes >= 60) return `осталось ${Math.floor(minutes / 60)} ч ${minutes % 60} мин`;
  if (minutes > 0) return `осталось ${minutes} мин`;
  return 'меньше минуты';
}

/**
 * Фаза дня на момент now. morningMinutes — с какого времени показывать
 * прогресс до первой пары (настройка «Показывать с»).
 */
export function status(now, lessons, morningMinutes = 420) {
  if (!lessons.length) return { phase: 'NO_CLASSES', title: 'Сегодня занятий нет', progress: 0 };

  const spans = lessons.map((lesson) => {
    const start = at(lesson.date, lesson.slot.start);
    const full = at(lesson.date, lesson.slot.end);
    const end = lesson.five === 'EARLY_END' ? new Date(full - 5 * 60000) : full;
    return { lesson, start, end, full };
  });

  const first = spans[0];
  if (now < first.start) {
    const from = at(now, morningMinutes);
    return {
      phase: 'BEFORE_FIRST',
      title: 'До первой пары',
      subtitle: `${first.lesson.subject} · ${remaining(now, first.start)}`,
      progress: progress(now, from, first.start),
      startsAt: from,
      endsAt: first.start,
      next: first.lesson,
    };
  }

  for (let i = 0; i < spans.length; i += 1) {
    const { lesson, start, end, full } = spans[i];
    const next = spans[i + 1];

    if (now >= start && now < end) {
      if (lesson.five === 'MIDDLE') {
        const breakStart = new Date(start.getTime() + Math.floor((full - start) / 60000 / 2) * 60000);
        const breakEnd = new Date(breakStart.getTime() + 5 * 60000);
        if (now >= breakStart && now < breakEnd) {
          return {
            phase: 'MIDDLE_BREAK',
            title: 'Пятиминутка',
            subtitle: remaining(now, breakEnd),
            progress: progress(now, breakStart, breakEnd),
            startsAt: breakStart,
            endsAt: breakEnd,
            current: lesson,
            next: lesson,
          };
        }
      }
      const room = lesson.room ? `Кабинет ${lesson.room} · ` : '';
      return {
        phase: 'LESSON',
        title: lesson.subject,
        subtitle: room + remaining(now, end),
        progress: progress(now, start, end),
        startsAt: start,
        endsAt: end,
        current: lesson,
        next: next ? next.lesson : undefined,
      };
    }

    if (next && now >= end && now < next.start) {
      return {
        phase: 'BETWEEN',
        title: 'Перерыв',
        subtitle: `Далее: ${next.lesson.subject} · ${remaining(now, next.start)}`,
        progress: progress(now, end, next.start),
        startsAt: end,
        endsAt: next.start,
        next: next.lesson,
      };
    }
  }

  return { phase: 'FINISHED', title: 'Учебный день завершён', progress: 1 };
}

/** Русские числительные: «1 занятие», «2 занятия», «5 занятий». */
export function plural(count, one, few, many) {
  const mod10 = count % 10;
  const mod100 = count % 100;
  if (mod10 === 1 && mod100 !== 11) return `${count} ${one}`;
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return `${count} ${few}`;
  return `${count} ${many}`;
}
