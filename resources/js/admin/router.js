import { createRouter, createWebHistory } from 'vue-router'
import { session } from './stores/session'
import LoginPage from './pages/LoginPage.vue'
import CategoriesPage from './pages/CategoriesPage.vue'
import StatisticsPage from './pages/StatisticsPage.vue'
import PagesPage from './pages/PagesPage.vue'
import SitePage from './pages/SitePage.vue'
import ProductsPage from './pages/ProductsPage.vue'
import ProductFormPage from './pages/ProductFormPage.vue'
import CustomersPage from './pages/CustomersPage.vue'
import OrdersPage from './pages/OrdersPage.vue'
import OrderPage from './pages/OrderPage.vue'
import OrderFormPage from './pages/OrderFormPage.vue'

const router = createRouter({
    history: createWebHistory('/admin'),
    scrollBehavior: () => ({ top: 0 }),
    routes: [
        { path: '/', redirect: { name: 'admin.statistics' } },
        { path: '/statistics', name: 'admin.statistics', component: StatisticsPage },
        { path: '/login', name: 'admin.login', component: LoginPage, meta: { guestOnly: true } },
        { path: '/categories', name: 'admin.categories', component: CategoriesPage },
        { path: '/products', name: 'admin.products', component: ProductsPage },
        { path: '/products/new', name: 'admin.products.create', component: ProductFormPage },
        { path: '/products/:id', name: 'admin.products.edit', component: ProductFormPage, props: true },
        { path: '/customers', name: 'admin.customers', component: CustomersPage },
        { path: '/pages', name: 'admin.pages', component: PagesPage },
        { path: '/site', name: 'admin.site', component: SitePage },
        { path: '/orders', name: 'admin.orders', component: OrdersPage },
        { path: '/orders/new', name: 'admin.orders.create', component: OrderFormPage },
        { path: '/orders/:id', name: 'admin.orders.show', component: OrderPage, props: true },
    ],
})

router.beforeEach(async (to) => {
    if (!session.state.ready) {
        await session.bootstrap()
    }

    if (!to.meta.guestOnly && !session.state.admin) {
        return { name: 'admin.login', query: { redirect: to.fullPath } }
    }

    if (to.meta.guestOnly && session.state.admin) {
        return { name: 'admin.statistics' }
    }

    return true
})

export default router
