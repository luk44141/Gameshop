const API = "api/";

let games = [];
let library = [];
let purchases = [];
let cart = [];
let currentUser = null;

let activeGenre = "Todos";

const money = new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency: "ARS",
    minimumFractionDigits: 2
});


/* =========================
   INICIO
========================= */

document.addEventListener("DOMContentLoaded", async () => {

    setupEvents();

    updateCartUI();

    await loadSession();

    await loadGames();

    if (currentUser) {
        await loadLibrary();
        await loadPurchases();
    }

    renderGames();
});


/* =========================
   EVENTOS
========================= */

function setupEvents() {

    document.querySelectorAll(".nav-link").forEach(button => {

        button.addEventListener("click", () => {

            const section = button.dataset.section;

            if (
                (section === "library" ||
                 section === "history" ||
                 section === "admin")
                &&
                !currentUser
            ) {
                openAuthModal();
                return;
            }

            if (
                section === "admin" &&
                currentUser?.rol !== "admin"
            ) {
                showNotification(
                    "No tenés permisos de administrador"
                );
                return;
            }

            showSection(section);
        });

    });


    document
        .getElementById("searchInput")
        .addEventListener("input", renderGames);


    document
        .getElementById("genreFilter")
        .addEventListener("change", event => {

            activeGenre = event.target.value;

            renderGames();
        });


    document
        .getElementById("sortFilter")
        .addEventListener("change", renderGames);


    document
        .getElementById("cartButton")
        .addEventListener("click", openCart);


    document
        .getElementById("authButton")
        .addEventListener("click", openAuthModal);


    document
        .getElementById("userButton")
        .addEventListener("click", openProfile);


    document
        .getElementById("logoutButton")
        .addEventListener("click", logout);


    document
        .getElementById("checkoutButton")
        .addEventListener("click", checkout);


    document
        .getElementById("refreshUsersButton")
        .addEventListener("click", loadAdminUsers);


    document.querySelectorAll("[data-close-modal]").forEach(element => {

        element.addEventListener("click", () => {

            closeModal(
                element.dataset.closeModal
            );

        });

    });


    document.querySelectorAll(".auth-tab").forEach(tab => {

        tab.addEventListener("click", () => {

            switchAuthTab(
                tab.dataset.authTab
            );

        });

    });


    document
        .getElementById("loginForm")
        .addEventListener("submit", login);


    document
        .getElementById("registerForm")
        .addEventListener("submit", register);

}


/* =========================
   API
========================= */

async function apiRequest(
    endpoint,
    options = {}
) {

    const response = await fetch(
        API + endpoint,
        {
            credentials: "same-origin",
            ...options,
            headers: {
                "Content-Type": "application/json",
                ...(options.headers || {})
            }
        }
    );

    let data;

    try {
        data = await response.json();
    } catch {
        throw new Error(
            "El servidor devolvió una respuesta inválida"
        );
    }

    if (!response.ok && data.message) {
        throw new Error(data.message);
    }

    return data;
}


/* =========================
   SESIÓN
========================= */

async function loadSession() {

    try {

        const data = await apiRequest(
            "sesion.php"
        );

        if (data.success && data.logged) {

            currentUser = data.user;

        } else {

            currentUser = null;

        }

        updateUserUI();

    } catch (error) {

        console.error(error);

        currentUser = null;

        updateUserUI();
    }
}


function updateUserUI() {

    const authButton =
        document.getElementById("authButton");

    const userButton =
        document.getElementById("userButton");

    const logoutButton =
        document.getElementById("logoutButton");

    const balanceBox =
        document.getElementById("balanceBox");

    const usernameValue =
        document.getElementById("usernameValue");

    const balanceValue =
        document.getElementById("balanceValue");

    const adminNav =
        document.getElementById("adminNav");


    if (!currentUser) {

        authButton.classList.remove("hidden");

        userButton.classList.add("hidden");

        logoutButton.classList.add("hidden");

        balanceBox.classList.add("hidden");

        adminNav.classList.add("hidden");

        return;
    }


    authButton.classList.add("hidden");

    userButton.classList.remove("hidden");

    logoutButton.classList.remove("hidden");

    balanceBox.classList.remove("hidden");


    usernameValue.textContent =
        currentUser.username;

    balanceValue.textContent =
        money.format(
            Number(currentUser.saldo)
        );


    if (currentUser.rol === "admin") {

        adminNav.classList.remove("hidden");

    } else {

        adminNav.classList.add("hidden");
    }
}


/* =========================
   JUEGOS
========================= */

async function loadGames() {

    try {

        const data = await apiRequest(
            "juegos.php"
        );

        games = Array.isArray(data)
            ? data
            : [];

        populateGenres();

    } catch (error) {

        console.error(error);

        document.getElementById(
            "gamesGrid"
        ).innerHTML = `
            <div class="empty-state">
                <h2>No se pudieron cargar los juegos</h2>
                <p>${escapeHTML(error.message)}</p>
            </div>
        `;
    }
}


function populateGenres() {

    const select =
        document.getElementById("genreFilter");

    const genres = [
        ...new Set(
            games
                .map(game => game.genero)
                .filter(Boolean)
        )
    ].sort();


    select.innerHTML = `
        <option value="Todos">
            Todos los géneros
        </option>
    `;


    genres.forEach(genre => {

        const option =
            document.createElement("option");

        option.value = genre;
        option.textContent = genre;

        select.appendChild(option);

    });

    select.value = activeGenre;
}


function renderGames() {

    const grid =
        document.getElementById("gamesGrid");

    const search =
        document
            .getElementById("searchInput")
            .value
            .toLowerCase()
            .trim();


    const sort =
        document
            .getElementById("sortFilter")
            .value;


    let filtered =
        games.filter(game => {

            const matchesSearch =
                game.nombre
                    .toLowerCase()
                    .includes(search);


            const matchesGenre =
                activeGenre === "Todos" ||
                game.genero === activeGenre;


            return matchesSearch &&
                   matchesGenre;
        });


    if (sort === "precioAsc") {

        filtered.sort(
            (a, b) =>
                Number(a.precio) -
                Number(b.precio)
        );

    } else if (sort === "precioDesc") {

        filtered.sort(
            (a, b) =>
                Number(b.precio) -
                Number(a.precio)
        );

    } else {

        filtered.sort(
            (a, b) =>
                a.nombre.localeCompare(
                    b.nombre
                )
        );
    }


    if (filtered.length === 0) {

        grid.innerHTML = `
            <div class="empty-state">
                <h2>No encontramos juegos</h2>
                <p>Probá con otra búsqueda o filtro.</p>
            </div>
        `;

        return;
    }


    grid.innerHTML =
        filtered
            .map(createGameCard)
            .join("");
}


function createGameCard(game) {

    const owned =
        library.some(
            item =>
                Number(item.id) ===
                Number(game.id)
        );


    const inCart =
        cart.some(
            item =>
                Number(item.id) ===
                Number(game.id)
        );


    let buttonText =
        "Agregar al carrito";

    let disabled = false;

    let buttonClass = "game-button";


    if (owned) {

        buttonText = "En biblioteca";

        disabled = true;

        buttonClass += " owned-button";

    } else if (inCart) {

        buttonText = "En carrito";

    }


    return `
        <article class="game-card ${owned ? "owned" : ""}">

            <div class="game-category">
                ${escapeHTML(game.genero || "Juego")}
            </div>

            <h2 class="game-title">
                ${escapeHTML(game.nombre)}
            </h2>

            <p class="game-description">
                ${escapeHTML(
                    game.descripcion ||
                    "Juego digital."
                )}
            </p>

            <div class="game-footer">

                <span class="game-price">
                    ${money.format(
                        Number(game.precio)
                    )}
                </span>

                <button
                    class="${buttonClass}"
                    onclick="addToCart(${Number(game.id)})"
                    ${disabled ? "disabled" : ""}
                >
                    ${buttonText}
                </button>

            </div>

        </article>
    `;
}


/* =========================
   CARRITO
========================= */

function addToCart(id) {

    if (!currentUser) {

        openAuthModal();

        return;
    }


    const game =
        games.find(
            item =>
                Number(item.id) ===
                Number(id)
        );


    if (!game) {

        showNotification(
            "Juego no encontrado"
        );

        return;
    }


    const owned =
        library.some(
            item =>
                Number(item.id) ===
                Number(id)
        );


    if (owned) {

        showNotification(
            "Ya tenés este juego"
        );

        return;
    }


    const alreadyInCart =
        cart.some(
            item =>
                Number(item.id) ===
                Number(id)
        );


    if (alreadyInCart) {

        showNotification(
            "El juego ya está en el carrito"
        );

        return;
    }


    cart.push(game);

    updateCartUI();

    renderGames();

    showNotification(
        "Juego agregado al carrito"
    );
}


function removeFromCart(id) {

    cart =
        cart.filter(
            game =>
                Number(game.id) !==
                Number(id)
        );


    updateCartUI();

    renderGames();

    renderCart();
}


function updateCartUI() {

    document.getElementById(
        "cartCount"
    ).textContent = cart.length;
}


function openCart() {

    renderCart();

    openModal("cartModal");
}


function renderCart() {

    const container =
        document.getElementById("cartItems");

    const totalElement =
        document.getElementById("cartTotal");


    if (cart.length === 0) {

        container.innerHTML = `
            <div class="empty-state">
                <h2>El carrito está vacío</h2>
                <p>Agregá algún juego desde la tienda.</p>
            </div>
        `;

        totalElement.textContent =
            money.format(0);

        return;
    }


    container.innerHTML =
        cart.map(game => `
            <div class="cart-item">

                <div class="cart-item-info">

                    <h3>
                        ${escapeHTML(game.nombre)}
                    </h3>

                    <p>
                        ${escapeHTML(game.genero || "")}
                    </p>

                </div>

                <div>
                    <strong>
                        ${money.format(
                            Number(game.precio)
                        )}
                    </strong>

                    <button
                        class="remove-cart-item"
                        onclick="removeFromCart(${Number(game.id)})"
                    >
                        Quitar
                    </button>
                </div>

            </div>
        `).join("");


    const total =
        cart.reduce(
            (sum, game) =>
                sum + Number(game.precio),
            0
        );


    totalElement.textContent =
        money.format(total);
}


/* =========================
   COMPRA
========================= */

async function checkout() {

    if (!currentUser) {

        closeModal("cartModal");

        openAuthModal();

        return;
    }


    if (cart.length === 0) {

        showNotification(
            "El carrito está vacío"
        );

        return;
    }


    const button =
        document.getElementById(
            "checkoutButton"
        );


    button.disabled = true;

    button.textContent =
        "Procesando...";


    try {

        const data =
            await apiRequest(
                "comprar.php",
                {
                    method: "POST",

                    body: JSON.stringify({
                        items: cart.map(
                            game => ({
                                id: Number(game.id)
                            })
                        )
                    })
                }
            );


        if (!data.success) {

            throw new Error(
                data.message ||
                "No se pudo realizar la compra"
            );
        }


        currentUser.saldo =
            Number(data.saldo);


        cart = [];


        updateUserUI();

        updateCartUI();

        closeModal("cartModal");

        await loadLibrary();

        await loadPurchases();

        renderGames();

        showNotification(
            "Compra realizada correctamente"
        );


    } catch (error) {

        showNotification(
            error.message
        );

    } finally {

        button.disabled = false;

        button.textContent =
            "Comprar";
    }
}


/* =========================
   BIBLIOTECA
========================= */

async function loadLibrary() {

    if (!currentUser) {

        library = [];

        renderLibrary();

        return;
    }


    try {

        const data =
            await apiRequest(
                "biblioteca.php"
            );


        library =
            data.success &&
            Array.isArray(data.juegos)
                ? data.juegos
                : [];


        renderLibrary();


    } catch (error) {

        console.error(error);

        library = [];

        renderLibrary();
    }
}


function renderLibrary() {

    const grid =
        document.getElementById(
            "libraryGrid"
        );


    if (!currentUser) {

        grid.innerHTML = `
            <div class="empty-state">
                <h2>Iniciá sesión</h2>
                <p>
                    Necesitás una cuenta para ver tu biblioteca.
                </p>
            </div>
        `;

        return;
    }


    if (library.length === 0) {

        grid.innerHTML = `
            <div class="empty-state">
                <h2>Tu biblioteca está vacía</h2>
                <p>
                    Los juegos que compres aparecerán acá.
                </p>
            </div>
        `;

        return;
    }


    const uniqueGames = [];

    const seen = new Set();


    library.forEach(game => {

        const id = Number(game.id);

        if (!seen.has(id)) {

            seen.add(id);

            uniqueGames.push(game);
        }

    });


    grid.innerHTML =
        uniqueGames.map(game => `

            <article class="game-card owned">

                <div class="game-category">
                    ${escapeHTML(
                        game.genero || "Juego"
                    )}
                </div>

                <h2 class="game-title">
                    ${escapeHTML(game.nombre)}
                </h2>

                <p class="game-description">
                    ${escapeHTML(
                        game.descripcion ||
                        "Juego digital."
                    )}
                </p>

                <div class="game-footer">

                    <span class="game-price">
                        Adquirido
                    </span>

                    <button
                        class="game-button owned-button"
                        disabled
                    >
                        En biblioteca
                    </button>

                </div>

            </article>

        `).join("");
}


/* =========================
   HISTORIAL
========================= */

async function loadPurchases() {

    if (!currentUser) {

        purchases = [];

        renderPurchases();

        return;
    }


    try {

        const data =
            await apiRequest(
                "compras.php"
            );


        purchases =
            data.success &&
            Array.isArray(data.compras)
                ? data.compras
                : [];


        renderPurchases();


    } catch (error) {

        console.error(error);

        purchases = [];

        renderPurchases();
    }
}


function renderPurchases() {

    const container =
        document.getElementById(
            "historyContainer"
        );


    if (!currentUser) {

        container.innerHTML = `
            <div class="empty-state">
                <h2>Iniciá sesión</h2>
                <p>
                    Necesitás una cuenta para ver tus compras.
                </p>
            </div>
        `;

        return;
    }


    if (purchases.length === 0) {

        container.innerHTML = `
            <div class="empty-state">
                <h2>No hay compras</h2>
                <p>
                    Tus compras aparecerán acá.
                </p>
            </div>
        `;

        return;
    }


    container.innerHTML =
        purchases.map(purchase => {

            const gamesHTML =
                (purchase.juegos || [])
                    .map(game => `
                        <div class="purchase-game">

                            <strong>
                                ${escapeHTML(
                                    game.nombre
                                )}
                            </strong>

                            <span>
                                ${money.format(
                                    Number(game.precio)
                                )}
                            </span>

                        </div>
                    `)
                    .join("");


            return `
                <article class="purchase-card">

                    <div class="purchase-header">

                        <div>
                            <div class="purchase-id">
                                COMPRA #${purchase.id}
                            </div>

                            <div class="purchase-date">
                                ${formatDate(
                                    purchase.fecha
                                )}
                            </div>
                        </div>

                        <div class="purchase-total">
                            ${money.format(
                                Number(purchase.total)
                            )}
                        </div>

                    </div>

                    <div class="purchase-games">
                        ${gamesHTML}
                    </div>

                </article>
            `;

        }).join("");
}


/* =========================
   ADMIN
========================= */

async function loadAdminUsers() {

    if (!currentUser) {

        showNotification(
            "Tenés que iniciar sesión"
        );

        return;
    }


    if (currentUser.rol !== "admin") {

        showNotification(
            "No tenés permisos de administrador"
        );

        return;
    }


    const tbody =
        document.getElementById(
            "usersTableBody"
        );


    tbody.innerHTML = `
        <tr>
            <td colspan="6">
                Cargando usuarios...
            </td>
        </tr>
    `;


    try {

        const data =
            await apiRequest(
                "admin.php"
            );


        if (!data.success) {

            throw new Error(
                data.message
            );
        }


        renderAdminUsers(
            data.usuarios || []
        );


    } catch (error) {

        tbody.innerHTML = `
            <tr>
                <td colspan="6">
                    ${escapeHTML(
                        error.message
                    )}
                </td>
            </tr>
        `;
    }
}


function renderAdminUsers(users) {

    const tbody =
        document.getElementById(
            "usersTableBody"
        );


    if (users.length === 0) {

        tbody.innerHTML = `
            <tr>
                <td colspan="6">
                    No hay usuarios.
                </td>
            </tr>
        `;

        return;
    }


    tbody.innerHTML =
        users.map(user => `

            <tr>

                <td>
                    ${Number(user.id)}
                </td>

                <td>
                    ${escapeHTML(
                        user.username
                    )}
                </td>

                <td>
                    ${escapeHTML(
                        user.email
                    )}
                </td>

                <td>
                    ${escapeHTML(
                        user.rol
                    )}
                </td>

                <td>

                    <input
                        class="saldo-input"
                        type="number"
                        min="0"
                        step="0.01"
                        value="${Number(
                            user.saldo
                        ).toFixed(2)}"
                        id="saldo-${Number(user.id)}"
                    >

                </td>

                <td>

                    <button
                        class="save-balance-button"
                        onclick="updateUserBalance(${Number(user.id)})"
                    >
                        Guardar
                    </button>

                </td>

            </tr>

        `).join("");
}


async function updateUserBalance(id) {

    const input =
        document.getElementById(
            `saldo-${id}`
        );


    if (!input) {
        return;
    }


    const saldo =
        Number(input.value);


    if (!Number.isFinite(saldo) || saldo < 0) {

        showNotification(
            "Ingresá un saldo válido"
        );

        return;
    }


    try {

        const data =
            await apiRequest(
                "admin.php",
                {
                    method: "POST",

                    body: JSON.stringify({
                        accion: "saldo",
                        id_usuario: Number(id),
                        saldo: saldo
                    })
                }
            );


        if (!data.success) {

            throw new Error(
                data.message
            );
        }


        if (
            currentUser &&
            Number(currentUser.id) ===
            Number(id)
        ) {

            currentUser.saldo = saldo;

            updateUserUI();
        }


        showNotification(
            "Saldo actualizado correctamente"
        );


        await loadAdminUsers();


    } catch (error) {

        showNotification(
            error.message
        );
    }
}


/* =========================
   AUTENTICACIÓN
========================= */

function openAuthModal() {

    switchAuthTab("login");

    clearAuthMessages();

    openModal("authModal");
}


function switchAuthTab(tab) {

    const loginForm =
        document.getElementById(
            "loginForm"
        );

    const registerForm =
        document.getElementById(
            "registerForm"
        );


    document
        .querySelectorAll(".auth-tab")
        .forEach(button => {

            button.classList.toggle(
                "active",
                button.dataset.authTab === tab
            );

        });


    if (tab === "login") {

        loginForm.classList.remove(
            "hidden"
        );

        registerForm.classList.add(
            "hidden"
        );

    } else {

        loginForm.classList.add(
            "hidden"
        );

        registerForm.classList.remove(
            "hidden"
        );
    }
}


async function login(event) {

    event.preventDefault();


    const email =
        document.getElementById(
            "loginEmail"
        ).value.trim();


    const password =
        document.getElementById(
            "loginPassword"
        ).value;


    const message =
        document.getElementById(
            "loginMessage"
        );


    message.textContent =
        "Iniciando sesión...";


    try {

        const data =
            await apiRequest(
                "login.php",
                {
                    method: "POST",

                    body: JSON.stringify({
                        email,
                        password
                    })
                }
            );


        if (!data.success) {

            throw new Error(
                data.message
            );
        }


        currentUser = data.user;


        updateUserUI();

        await loadLibrary();

        await loadPurchases();

        renderGames();

        closeModal("authModal");

        event.target.reset();

        showNotification(
            `Sesión iniciada como ${currentUser.username}`
        );


    } catch (error) {

        message.textContent =
            error.message;
    }
}


async function register(event) {

    event.preventDefault();


    const username =
        document.getElementById(
            "registerUsername"
        ).value.trim();


    const email =
        document.getElementById(
            "registerEmail"
        ).value.trim();


    const password =
        document.getElementById(
            "registerPassword"
        ).value;


    const message =
        document.getElementById(
            "registerMessage"
        );


    message.textContent =
        "Creando cuenta...";


    try {

        const data =
            await apiRequest(
                "register.php",
                {
                    method: "POST",

                    body: JSON.stringify({
                        username,
                        email,
                        password
                    })
                }
            );


        if (!data.success) {

            throw new Error(
                data.message
            );
        }


        currentUser = data.user;


        updateUserUI();

        library = [];

        purchases = [];

        renderLibrary();

        renderPurchases();

        renderGames();

        closeModal("authModal");

        event.target.reset();

        showNotification(
            "Cuenta creada correctamente"
        );


    } catch (error) {

        message.textContent =
            error.message;
    }
}


async function logout() {

    try {

        await apiRequest(
            "logout.php",
            {
                method: "POST"
            }
        );

    } catch (error) {

        console.error(error);
    }


    currentUser = null;

    library = [];

    purchases = [];

    cart = [];


    updateUserUI();

    updateCartUI();

    renderGames();

    renderLibrary();

    renderPurchases();

    showSection("store");

    showNotification(
        "Sesión cerrada"
    );
}


/* =========================
   PERFIL
========================= */

function openProfile() {

    if (!currentUser) {
        return;
    }


    document.getElementById(
        "profileUsername"
    ).textContent =
        currentUser.username;


    document.getElementById(
        "profileEmail"
    ).textContent =
        currentUser.email;


    document.getElementById(
        "profileBalance"
    ).textContent =
        money.format(
            Number(currentUser.saldo)
        );


    document.getElementById(
        "profileRole"
    ).textContent =
        currentUser.rol;


    openModal("profileModal");
}


/* =========================
   NAVEGACIÓN
========================= */

function showSection(section) {

    const sections = {
        store: "storeSection",
        library: "librarySection",
        history: "historySection",
        admin: "adminSection"
    };


    Object.values(sections)
        .forEach(id => {

            document
                .getElementById(id)
                .classList.remove(
                    "active-section"
                );

        });


    const selected =
        sections[section];


    if (!selected) {
        return;
    }


    document
        .getElementById(selected)
        .classList.add(
            "active-section"
        );


    document
        .querySelectorAll(".nav-link")
        .forEach(button => {

            button.classList.toggle(
                "active",
                button.dataset.section === section
            );

        });


    if (section === "library") {

        loadLibrary();

    } else if (section === "history") {

        loadPurchases();

    } else if (section === "admin") {

        loadAdminUsers();
    }


    window.scrollTo({
        top: 0,
        behavior: "smooth"
    });
}


/* =========================
   MODALES
========================= */

function openModal(id) {

    document
        .getElementById(id)
        .classList.remove(
            "hidden"
        );

    document.body.style.overflow =
        "hidden";
}


function closeModal(id) {

    document
        .getElementById(id)
        .classList.add(
            "hidden"
        );

    document.body.style.overflow =
        "";
}


function clearAuthMessages() {

    document.getElementById(
        "loginMessage"
    ).textContent = "";

    document.getElementById(
        "registerMessage"
    ).textContent = "";
}


/* =========================
   NOTIFICACIONES
========================= */

let notificationTimeout;


function showNotification(message) {

    const notification =
        document.getElementById(
            "notification"
        );


    const text =
        document.getElementById(
            "notificationText"
        );


    text.textContent = message;

    notification.classList.remove(
        "hidden"
    );


    clearTimeout(
        notificationTimeout
    );


    notificationTimeout =
        setTimeout(() => {

            notification.classList.add(
                "hidden"
            );

        }, 3000);
}


/* =========================
   UTILIDADES
========================= */

function formatDate(dateString) {

    if (!dateString) {
        return "-";
    }


    const date =
        new Date(
            dateString.replace(
                " ",
                "T"
            )
        );


    if (Number.isNaN(date.getTime())) {
        return dateString;
    }


    return date.toLocaleString(
        "es-AR",
        {
            dateStyle: "medium",
            timeStyle: "short"
        }
    );
}


function escapeHTML(value) {

    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}