import { createRouter, createWebHistory } from 'vue-router'

export const APP_NAME = 'Learning and Development Needs Assessment'

// `nav` puts a route in the drawer; `section` groups drawer items under a label.
export const routes = [
  { path: '/', name: 'dashboard', component: () => import('./views/DashboardView.vue'),
    meta: { title: 'Dashboard', icon: 'squares', nav: true } },
  { path: '/competency-dictionary', name: 'competency-dictionary', component: () => import('./views/CompetencyDictionaryView.vue'),
    meta: { title: 'Competency Dictionary', icon: 'book-open', nav: true, searchPlaceholder: 'Search competencies' } },
  { path: '/positions', name: 'positions', component: () => import('./views/PositionsView.vue'),
    meta: { title: 'Positions', icon: 'users', nav: true, searchPlaceholder: 'Search positions' } },
  { path: '/library', name: 'library', component: () => import('./views/LibraryView.vue'),
    meta: { title: 'Library', icon: 'library', nav: true, searchPlaceholder: 'Search trainings' } },

  { path: '/assessment', name: 'assessment', component: () => import('./views/AssessmentView.vue'),
    meta: { title: 'Assessment', icon: 'clipboard-check', nav: true, section: 'Assessment' } },
  { path: '/enrollment-requests', name: 'enrollment-requests', component: () => import('./views/EnrollmentRequestsView.vue'),
    meta: { title: 'Enrollment Requests', icon: 'check', nav: true, section: 'CETAR', searchPlaceholder: 'Search requests' } },
  { path: '/self-assessment', redirect: (to) => ({ path: '/assessment', query: to.query }) },
  { path: '/enroll', redirect: (to) => ({ path: '/assessment', query: to.query }) },

  { path: '/competency-map', redirect: '/positions' },
  { path: '/gap-review', redirect: '/' },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('./views/NotFoundView.vue'),
    meta: { title: 'Page not found' } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} | ${APP_NAME}` : APP_NAME
})

export default router
