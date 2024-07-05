document.addEventListener('DOMContentLoaded', function() {
    window.fillEmailRecipients = function() {
        fetch('get_emails.php')
            .then(response => response.json())
            .then(data => {
                document.getElementById('email_recipients').value = data.join(', ');
            })
            .catch(error => console.error('Error fetching emails:', error));
    };

    window.confirmDelete = function(event, userId, paymentStatus, refundStatus, userName) {
        event.preventDefault();

        if (paymentStatus === 'Paid' && refundStatus !== 'refunded') {
            alert("Cannot delete a paid user. Please issue a refund first.");
            return;
        }

        if (confirm(`Are you sure you want to delete the user "${userName}"? This action cannot be undone. It is irreversible.`)) {
            fetch('delete_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: userId })
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                location.reload();
            })
            .catch(error => console.error('Error deleting user, please report this error to the dev:', error));
        }
    };

    window.editUser = function(userId) {
        window.location.href = 'edit_user.php?id=' + userId;
    };

    const stripeSearchForm = document.getElementById('stripe-search-form');
    const stripeSearchInput = document.getElementById('stripe-search');
    const transactionsContainer = document.getElementById('transactions-container');
    const prevPageButton = document.getElementById('prev-page');
    const nextPageButton = document.getElementById('next-page');
    const currentPageSpan = document.getElementById('current-page');
    const totalPagesSpan = document.getElementById('total-pages');
    const pageInfo = document.getElementById('page-info');

    const ITEMS_PER_PAGE = 2;
    let currentPage = 1;
    let filteredTransactions = [];

    if (stripeSearchForm) {
        stripeSearchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            searchStripeTransactions();
        });
    }

    if (stripeSearchInput) {
        stripeSearchInput.addEventListener('input', searchStripeTransactions);
    }

    if (prevPageButton) {
        prevPageButton.addEventListener('click', goToPreviousPage);
    }

    if (nextPageButton) {
        nextPageButton.addEventListener('click', goToNextPage);
    }

    function searchStripeTransactions() {
        const searchTerm = stripeSearchInput.value.toLowerCase();
        filteredTransactions = Object.entries(stripeDetails).filter(([id, details]) => {
            const text = `${id} ${details.amount} ${details.currency} ${details.customer} ${details.status}`.toLowerCase();
            return text.includes(searchTerm);
        });
        currentPage = 1;
        displayTransactions();
    }

    function displayTransactions() {
        const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
        const endIndex = startIndex + ITEMS_PER_PAGE;
        const transactionsToShow = filteredTransactions.slice(startIndex, endIndex);

        transactionsContainer.innerHTML = transactionsToShow.map(([id, details]) => `
            <div class="transaction">
                <p><strong>Payment Intent ID:</strong> ${id}</p>
                <p><strong>Amount:</strong> ${details.amount / 100} ${details.currency.toUpperCase()}</p>
                <p><strong>Customer:</strong> ${details.customer}</p>
                <p><strong>Status:</strong> ${details.status}</p>
            </div>
        `).join('');

        updatePaginationControls();
    }

    function updatePaginationControls() {
        const totalPages = Math.ceil(filteredTransactions.length / ITEMS_PER_PAGE);
        currentPageSpan.textContent = currentPage;
        totalPagesSpan.textContent = totalPages;

        prevPageButton.style.display = currentPage === 1 ? 'none' : 'inline-block';
        nextPageButton.style.display = currentPage === totalPages ? 'none' : 'inline-block';
        pageInfo.style.display = totalPages > 1 ? 'inline' : 'none';
    }

    function goToPreviousPage() {
        if (currentPage > 1) {
            currentPage--;
            displayTransactions();
        }
    }

    function goToNextPage() {
        const totalPages = Math.ceil(filteredTransactions.length / ITEMS_PER_PAGE);
        if (currentPage < totalPages) {
            currentPage++;
            displayTransactions();
        }
    }

    filteredTransactions = Object.entries(stripeDetails);
    displayTransactions();

    const columnCheckboxes = document.querySelectorAll('#column-checkboxes input[type="checkbox"]');
    const columnTable = document.querySelector('#user-table tbody');

    function updateColumnVisibility() {
        const selectedColumns = Array.from(columnCheckboxes)
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.value);

        const headers = document.querySelectorAll('#user-table th');
        headers.forEach((header, index) => {
            const column = header.dataset.sort;
            if (column === undefined) return; // Skip the actions column
            const isVisible = selectedColumns.includes(column);
            header.style.display = isVisible ? '' : 'none';
            document.querySelectorAll(`#user-table td:nth-child(${index + 1})`).forEach(cell => {
                cell.style.display = isVisible ? '' : 'none';
            });
        });
    }

    columnCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateColumnVisibility);
    });

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                updateColumnVisibility();
            }
        });
    });

    const config = { childList: true, subtree: true };
    observer.observe(columnTable, config);

updateColumnVisibility();

    const userTable = document.querySelector('#user-table table');
    const userHeaders = userTable.querySelectorAll('.sortable-header');

    userHeaders.forEach(header => {
        header.addEventListener('click', () => {
            const column = header.dataset.sort;
            const order = header.classList.contains('asc') ? 'desc' : 'asc';
            
            userHeaders.forEach(h => h.classList.remove('asc', 'desc'));
            
            header.classList.add(order);
            
        const rows = Array.from(userTable.querySelectorAll('tbody tr'));
        rows.sort((a, b) => {
            const aValue = a.querySelector(`.${column}`).textContent;
            const bValue = b.querySelector(`.${column}`).textContent;
            return order === 'asc' ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
            });
            
        rows.forEach(row => userTable.querySelector('tbody').appendChild(row));
    });
    });

    const donationsTable = document.querySelector('.donations-table');
    const donationsHeaders = donationsTable.querySelectorAll('.sortable-header');

    donationsHeaders.forEach(header => {
        header.addEventListener('click', () => {
            const column = header.dataset.sort;
            const order = header.classList.contains('asc') ? 'desc' : 'asc';
            
            donationsHeaders.forEach(h => h.classList.remove('asc', 'desc'));
            
            header.classList.add(order);
            
            const rows = Array.from(donationsTable.querySelectorAll('tbody tr'));
            rows.sort((a, b) => {
                const aValue = a.querySelector(`.${column}`).textContent;
                const bValue = b.querySelector(`.${column}`).textContent;
            return order === 'asc' ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
            });
            
            rows.forEach(row => donationsTable.querySelector('tbody').appendChild(row));
        });
    });
});

function confirmRefund(paymentIntentId, userId) {
        if (confirm('Are you sure you want to refund this payment? The user will still remain as having signed up until you delete their record.')) {
            refundPayment(paymentIntentId, userId, 'user');
        }
}

function refundPayment(paymentIntentId, userId, type) {
        const params = new URLSearchParams();
        params.append('payment_intent_id', paymentIntentId);
        if (userId) {
            params.append('user_id', userId);
        }
        params.append('type', type);

        fetch('process_refund.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: params.toString()
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Refund processed successfully');
            location.reload();
            } else {
                alert('Error processing refund: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing the refund');
        });
}

function fillEmailFields() {
        var templateKey = document.getElementById('email_template').value;
        if (templateKey) {
            var template = emailTemplates[templateKey];
            document.getElementById('email_subject').value = template.subject;
            document.getElementById('email_message').value = template.message;
        } else {
            document.getElementById('email_subject').value = '';
            document.getElementById('email_message').value = '';
        }
}

function confirmDonationRefund(paymentIntentId) {
        if (confirm('Are you sure you want to refund this donation?')) {
            refundPayment(paymentIntentId, null, 'donation');
        }
}





let currentPage = 1;
let currentSort = 'name';
let currentOrder = 'ASC';
let currentSearch = '';

function loadUsers(page = 1, sort = currentSort, order = currentOrder, search = currentSearch) {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `fetch_users.php?page=${page}&sort=${sort}&order=${order}&search=${search}`, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            updateUserTable(response.users);
            updatePagination(response.currentPage, response.totalPages);
            currentPage = response.currentPage;
            currentSort = sort;
            currentOrder = order;
            currentSearch = search;
        } else {
            console.error("Error fetching users:", xhr.statusText);
        }
    };
    xhr.onerror = function() {
        console.error("Network error occurred");
    };
    xhr.send();
}

function updateUserTable(users) {
    const tbody = document.querySelector('#user-table tbody');
    tbody.innerHTML = '';
    users.forEach(user => {
        const row = document.createElement('tr');
        row.className = user.refund_status === 'refunded' ? 'refunded-row' : '';
        row.innerHTML = `
            <td class="name">${escapeHtml(user.name)}</td>
            <td class="email">${escapeHtml(user.email)}</td>
            <td class="stripe_customer_id">${escapeHtml(user.stripe_customer_id)}</td>
            <td class="stripe_payment_intent_id">${escapeHtml(user.stripe_payment_intent_id)}</td>
            <td class="description">${escapeHtml(user.description)}</td>
            <td class="payment_status ${user.payment_status.toLowerCase()}">${escapeHtml(user.payment_status)}</td>
            <td class="created_at">${escapeHtml(user.created_at)}</td>
            <td class="updated_at">${escapeHtml(user.updated_at)}</td>
            <td>
                <button onclick="editUser(${user.id})">Edit</button>
                <button class="delete-button ${(user.payment_status === 'Paid' && user.refund_status !== 'refunded') ? 'disabled' : ''}" 
                        onclick="confirmDelete(event, ${user.id}, '${user.payment_status}', '${user.refund_status}', '${escapeHtml(user.name)}')">
                    Delete
                </button>
                ${user.stripe_payment_intent_id && user.refund_status !== 'refunded' ? 
                    `<button class="refund-button" onclick="confirmRefund('${escapeHtml(user.stripe_payment_intent_id)}', ${user.id})">Refund</button>` : 
                    ''}
            </td>
        `;
        tbody.appendChild(row);
    });
    }

function updatePagination(currentPage, totalPages) {
    const pagination = document.querySelector('.pagination');
    pagination.innerHTML = '';
    currentPage = parseInt(currentPage);
    totalPages = parseInt(totalPages);

    if (currentPage > 1) {
        const prevButton = document.createElement('button');
        prevButton.textContent = 'Previous';
        prevButton.onclick = () => loadUsers(currentPage - 1);
        pagination.appendChild(prevButton);
    }
    const pageInfo = document.createElement('span');
    pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
    pagination.appendChild(pageInfo);
    if (currentPage < totalPages) {
        const nextButton = document.createElement('button');
        nextButton.textContent = 'Next';
        nextButton.onclick = () => loadUsers(currentPage + 1);
        pagination.appendChild(nextButton);
    }
}

document.querySelectorAll('.sortable-header').forEach(header => {
    header.addEventListener('click', function() {
        const sort = this.dataset.sort;
        const order = currentSort === sort && currentOrder === 'ASC' ? 'DESC' : 'ASC';
        loadUsers(1, sort, order);
    });
});

document.querySelector('#search-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const search = document.querySelector('#user-search').value;
    loadUsers(1, currentSort, currentOrder, search);
});

function escapeHtml(unsafe) {
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
});