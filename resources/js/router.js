import { createRouter, createWebHistory } from 'vue-router'
import { session } from './stores/session'
import CatalogPage from './pages/CatalogPage.vue'
import ProductPage from './pages/ProductPage.vue'
import LoginPage from './pages/LoginPage.vue'
import RegisterPage from './pages/RegisterPage.vue'
import CartPage from './pages/CartPage.vue'
import CheckoutPage from './pages/CheckoutPage.vue'
import PaymentPage from './pages/PaymentPage.vue'
import OrdersPage from './pages/OrdersPage.vue'

const router = createRouter({
    history: createWebHistory(),
    scrollBehavior: () => ({ top: 0 }),
    routes: [
        { path: '/', name: 'catalog', component: CatalogPage },
        { path: '/products/:slug', name: 'product', component: ProductPage, props: true },
        { path: '/login', name: 'login', component: LoginPage, meta: { guestOnly: true } },
        { path: '/register', name: 'register', component: RegisterPage, meta: { guestOnly: true } },
        { path: '/cart', name: 'cart', component: CartPage },
        { path: '/checkout', name: 'checkout', component: CheckoutPage, meta: { auth: true } },
        { path: '/orders', name: 'orders', component: OrdersPage, meta: { auth: true } },
        { path: '/orders/:number/payment', name: 'payment', component: PaymentPage, props: true, meta: { auth: true } },
    ],
})

router.beforeEach(async (to) => {
    if (!session.state.ready) {
        await session.bootstrap()
    }

    if (to.meta.auth && !session.state.customer) {
        return { name: 'login', query: { redirect: to.fullPath } }
    }

    if (to.meta.guestOnly && session.state.customer) {
        return { name: 'catalog' }
    }

    return true
})

export default router
