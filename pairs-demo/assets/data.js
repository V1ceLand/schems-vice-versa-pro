/*
 * Демонстрационные данные. Звонки и чётная неделя взяты из SeedData.kt
 * приложения; нечётная неделя, задания, чат и участники дописаны для показа —
 * настоящих людей и групп здесь нет.
 */

export const GROUP = {
  name: 'Виноградарство, 2 курс',
  inviteCode: 'PAIRS-42',
  members: 24,
  plan: 'Group Pro',
  storageUsedMb: 1240,
  storageLimitMb: 5120,
};

/** Звонки: номер пары, начало и конец в минутах от полуночи. */
export const SLOTS = [
  { number: 1, start: 8 * 60, end: 9 * 60 + 35, five: 'EARLY_END' },
  { number: 2, start: 9 * 60 + 55, end: 11 * 60 + 30, five: 'EARLY_END' },
  { number: 3, start: 12 * 60, end: 13 * 60 + 35, five: 'MIDDLE' },
  { number: 4, start: 13 * 60 + 45, end: 15 * 60 + 20, five: 'EARLY_END' },
  { number: 5, start: 15 * 60 + 30, end: 17 * 60 + 5, five: 'EARLY_END' },
  { number: 6, start: 17 * 60 + 15, end: 18 * 60 + 50, five: 'NONE' },
  { number: 7, start: 19 * 60, end: 20 * 60 + 35, five: 'NONE' },
];

const MDK = 'МДК 02.01 — Технологии контроля за развитием культуры винограда';

const L = (weekType, day, pair, subject, teacher, room, five) =>
  ({ weekType, day, pair, subject, teacher, room, five: five || 'INHERIT' });

export const LESSONS = [
  // Чётная неделя — как в SeedData.kt.
  L('EVEN', 1, 2, 'Ботаника и физиология растений', 'Фадеева Е. В.', '207'),
  L('EVEN', 1, 3, 'Основы экономики, менеджмента и маркетинга', 'Тихно Н. В.', '108'),
  L('EVEN', 1, 4, MDK, 'Арышева С. Г.', '210'),
  L('EVEN', 2, 2, 'Экологические основы природопользования', 'Петрова Т. А.', '25'),
  L('EVEN', 2, 3, 'История России', 'Эйснер Т. В.', '23'),
  L('EVEN', 2, 4, 'Иностранный язык', 'Фасульян Г. А.', '17'),
  L('EVEN', 3, 1, MDK, 'Арышева С. Г.', '210'),
  L('EVEN', 3, 2, MDK, 'Арышева С. Г.', '209'),
  L('EVEN', 3, 3, 'Физическая культура', 'Егорова М. Л.', 'с/з', 'NONE'),
  L('EVEN', 3, 4, 'Классный час', 'Пономаренко А. Ю.', 'библиотека'),
  L('EVEN', 4, 5, MDK, 'Арышева С. Г.', '210'),
  L('EVEN', 4, 6, MDK, 'Арышева С. Г.', '210'),
  L('EVEN', 4, 7, MDK, 'Арышева С. Г.', '210'),
  L('EVEN', 5, 2, MDK, 'Арышева С. Г.', '210'),
  L('EVEN', 5, 3, MDK, 'Арышева С. Г.', '210'),
  L('EVEN', 5, 4, 'Безопасность жизнедеятельности', 'Цунин А. А.', '11'),
  L('EVEN', 6, 1, 'История России', 'Эйснер Т. В.', '23'),
  L('EVEN', 6, 2, 'Ботаника и физиология растений', 'Фадеева Е. В.', '207'),
  L('EVEN', 6, 3, MDK, 'Арышева С. Г.', '209'),

  // Нечётная неделя — вымышленная, чтобы чередование было видно.
  L('ODD', 1, 1, 'Иностранный язык', 'Фасульян Г. А.', '17'),
  L('ODD', 1, 2, MDK, 'Арышева С. Г.', '210'),
  L('ODD', 1, 3, 'Математика', 'Ковалёва И. П.', '112'),
  L('ODD', 2, 2, 'Ботаника и физиология растений', 'Фадеева Е. В.', '207'),
  L('ODD', 2, 3, 'Основы экономики, менеджмента и маркетинга', 'Тихно Н. В.', '108'),
  L('ODD', 2, 4, 'Физическая культура', 'Егорова М. Л.', 'с/з', 'NONE'),
  L('ODD', 3, 2, MDK, 'Арышева С. Г.', '210'),
  L('ODD', 3, 3, MDK, 'Арышева С. Г.', '210'),
  L('ODD', 3, 4, 'История России', 'Эйснер Т. В.', '23'),
  L('ODD', 4, 2, 'Экологические основы природопользования', 'Петрова Т. А.', '25'),
  L('ODD', 4, 3, MDK, 'Арышева С. Г.', '209'),
  L('ODD', 4, 4, 'Безопасность жизнедеятельности', 'Цунин А. А.', '11'),
  L('ODD', 5, 1, MDK, 'Арышева С. Г.', '210'),
  L('ODD', 5, 2, MDK, 'Арышева С. Г.', '210'),
  L('ODD', 5, 3, 'Математика', 'Ковалёва И. П.', '112'),
  L('ODD', 6, 2, 'Классный час', 'Пономаренко А. Ю.', 'библиотека'),
];

/**
 * Замена на день. Ключ — смещение в днях от «сегодня» демонстрации: так
 * замена всегда попадает на ближайший будний день, в какой бы день недели
 * ни открыли сайт.
 */
export const OVERRIDE_OFFSET_DAYS = 1;
export const OVERRIDE = [
  { pair: 2, subject: 'Консультация по курсовой', teacher: 'Арышева С. Г.', room: '210', five: 'INHERIT' },
  { pair: 3, subject: 'История России', teacher: 'Эйснер Т. В.', room: '23', five: 'INHERIT' },
];

/** Задания. dueOffset — на сколько дней от «сегодня» сдвинут срок. */
export const HOMEWORK = [
  {
    id: 'h1', subject: MDK, title: 'Схема обрезки на плодоношение',
    description: 'Начертить на А3, подписать глазки и сучки замещения.',
    dueOffset: -2, dueTime: '08:00', done: false, reminder: 120, photo: true, scope: 'group',
  },
  {
    id: 'h2', subject: 'Иностранный язык', title: 'Топик «Виноделие региона»',
    description: '12–15 предложений, сдать устно.',
    dueOffset: 0, dueTime: '13:45', done: false, reminder: 60, scope: 'group',
  },
  {
    id: 'h3', subject: 'Основы экономики, менеджмента и маркетинга', title: 'Расчёт себестоимости',
    description: 'Таблица в Excel по варианту из методички.',
    dueOffset: 1, dueTime: '12:00', done: false, reminder: null, scope: 'group',
  },
  {
    id: 'h4', subject: 'История России', title: 'Конспект §14',
    description: 'Реформы 1860-х, тезисно.',
    dueOffset: 2, dueTime: '09:55', done: false, reminder: 1440, scope: 'personal',
  },
  {
    id: 'h5', subject: 'Ботаника и физиология растений', title: 'Гербарий, 10 листов',
    description: '', dueOffset: 4, dueTime: '09:55', done: false, reminder: null, scope: 'group',
  },
  {
    id: 'h6', subject: 'Безопасность жизнедеятельности', title: 'Тест по разделу 3',
    description: '', dueOffset: -1, dueTime: '13:45', done: true, reminder: null, scope: 'group',
  },
  {
    id: 'h7', subject: 'Физическая культура', title: 'Справка после болезни',
    description: '', dueOffset: -3, dueTime: '12:00', done: true, reminder: null, scope: 'personal',
  },
];

/** Темы форума. */
export const THREADS = [
  { id: 't1', title: 'Общий чат', kind: 'chat', unread: 3, last: 'Аня: скинула фото доски' },
  { id: 't2', title: 'МДК 02.01 — конспекты', kind: 'topic', unread: 0, last: 'Ильдар: лекция 9 в pdf' },
  { id: 't3', title: 'Курсовая: вопросы', kind: 'topic', unread: 1, last: 'Староста: дедлайн сдвинули' },
  { id: 't4', title: 'Практика летом', kind: 'topic', unread: 0, last: 'Марина: список хозяйств' },
];

/** Сообщения общего чата: текст, вложение, голосовое, реакция, правка. */
export const MESSAGES = [
  {
    id: 'm1', author: 'Марина', initials: 'М', mine: false, at: '12:41',
    text: 'Кто-нибудь записал, что задали по МДК?',
  },
  {
    id: 'm2', author: 'Ильдар', initials: 'И', mine: false, at: '12:44',
    text: 'Схему обрезки, на А3. Вот фото доски',
    attachment: { kind: 'image', name: 'IMG_2841.jpg', size: '2,4 МБ' },
    reactions: [{ emoji: '👍', count: 6 }, { emoji: '🙏', count: 2 }],
  },
  {
    id: 'm3', author: 'Аня', initials: 'А', mine: false, at: '12:52',
    voice: { seconds: 27 },
    text: '',
  },
  {
    id: 'm4', author: 'Вы', initials: 'В', mine: true, at: '13:02',
    text: 'Спасибо! Добавил в задания, срок — пятница',
    edited: true,
    read: 18,
  },
  {
    id: 'm5', author: 'Староста', initials: 'С', mine: false, at: '13:10',
    text: 'Завтра вторая пара — консультация вместо расписания, поставил замену на день',
    pinned: true,
  },
];

/** Участники и роли. */
export const MEMBERS = [
  { name: 'Пономаренко А. Ю.', initials: 'АП', role: 'Администратор', note: 'куратор группы' },
  { name: 'Смирнова Е.', initials: 'ЕС', role: 'Староста', note: 'ведёт расписание' },
  { name: 'Гизатуллин И.', initials: 'ИГ', role: 'Зам старосты', note: '' },
  { name: 'Вы', initials: 'В', role: 'Ученик', note: 'личное право: создавать темы' },
  { name: 'Ещё 20 участников', initials: '+20', role: 'Ученик', note: '' },
];

/** Права ролей — из permissions.py (DEFAULT_ROLE_PERMISSIONS). */
export const PERMISSIONS = {
  columns: ['Администратор', 'Куратор', 'Староста', 'Зам старосты', 'Ученик'],
  rows: [
    { name: 'Настройки группы', key: 'manageGroup', bits: [1, 0, 0, 0, 0] },
    { name: 'Участники', key: 'manageMembers', bits: [1, 1, 0, 0, 0] },
    { name: 'Роли', key: 'manageRoles', bits: [1, 0, 0, 0, 0] },
    { name: 'Расписание', key: 'manageSchedule', bits: [1, 1, 1, 1, 0] },
    { name: 'Задания на всю группу', key: 'manageHomeworkGlobal', bits: [1, 1, 1, 1, 0] },
    { name: 'Оформление', key: 'manageAppearance', bits: [1, 1, 1, 0, 0] },
    { name: 'Создавать темы', key: 'createTopics', bits: [1, 1, 1, 1, 0] },
    { name: 'Модерация чата', key: 'moderateChat', bits: [1, 1, 1, 0, 0] },
    { name: 'Оплата', key: 'manageBilling', bits: [1, 0, 0, 0, 0] },
  ],
};

/** Тарифы — из billing.py (PLAN_PRICES_KOPECKS, PLAN_BASE_STORAGE_GB). */
export const PLANS = [
  {
    code: 'FREE', name: 'Бесплатно', month: 0, year: 0, storage: '—',
    note: 'Расписание, задания и уведомления — без ограничений и без входа.',
    features: [
      'Расписание, звонки, чётность недель',
      'Задания и напоминания',
      'Уведомления и утренний будильник',
      'Работа офлайн, импорт и экспорт JSON',
      'Группа, роли и общий чат',
    ],
  },
  {
    code: 'BASIC', name: 'Group Pro', month: 500, year: 5000, storage: '5 ГБ',
    highlight: true,
    note: 'Общее хранилище на всю группу: фото, файлы и голосовые в чате.',
    features: [
      'Всё из бесплатного',
      '5 ГБ вложений на группу',
      'История правок сообщений',
      'Фото преподавателей и заданий',
      'Приоритет в синхронизации',
    ],
  },
  {
    code: 'PRO', name: 'Group Pro+', month: 800, year: 8000, storage: '20 ГБ',
    note: 'Для потоков и больших групп с активным чатом.',
    features: [
      'Всё из Group Pro',
      '20 ГБ вложений на группу',
      'Расширенная модерация',
      'Несколько расписаний в одной группе',
      'Поддержка по почте',
    ],
  },
];
