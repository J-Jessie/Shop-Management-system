<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100">

    <div id="root"></div>

    <script src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    
    <!-- Firebase Libraries -->
    <script type="module">
        import { initializeApp } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-app.js";
        import { getAuth, signInAnonymously, signInWithCustomToken, onAuthStateChanged } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-auth.js";
        import { getFirestore, collection, onSnapshot } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-firestore.js";
        
        window.firebase = { initializeApp, getAuth, signInAnonymously, signInWithCustomToken, onAuthStateChanged, getFirestore, collection, onSnapshot };

        const firebaseConfig = typeof __firebase_config !== 'undefined' ? JSON.parse(__firebase_config) : {};
        const appId = typeof __app_id !== 'undefined' ? __app_id : 'default-app-id';
        const initialAuthToken = typeof __initial_auth_token !== 'undefined' ? __initial_auth_token : null;

        let db = null;
        let auth = null;
        let userId = null;

        if (Object.keys(firebaseConfig).length > 0) {
            const app = firebase.initializeApp(firebaseConfig);
            db = firebase.getFirestore(app);
            auth = firebase.getAuth(app);
            window.db = db;
            window.auth = auth;

            onAuthStateChanged(auth, async (user) => {
                if (!user) {
                    if (initialAuthToken) {
                        try {
                            await firebase.signInWithCustomToken(auth, initialAuthToken);
                        } catch (error) {
                            console.error("Error signing in with custom token:", error);
                        }
                    } else {
                        try {
                            await firebase.signInAnonymously(auth);
                        } catch (error) {
                            console.error("Error signing in anonymously:", error);
                        }
                    }
                }
                userId = auth.currentUser?.uid || crypto.randomUUID();
                window.userId = userId;
            });
        } else {
            console.warn("Firebase config not provided. Functionality may be limited.");
            userId = crypto.randomUUID();
            window.userId = userId;
        }
    </script>
    
    <script type="text/babel">
        const { useState, useEffect } = React;
        const root = ReactDOM.createRoot(document.getElementById('root'));

        const icons = {
            Users: (props) => (<svg {...props} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="lucide lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M22 7a4 4 0 0 0-3.87-3"/></svg>),
            Clock: (props) => (<svg {...props} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="lucide lucide-clock-5"><circle cx="12" cy="12" r="10"/><path d="M12 8v5"/><path d="M16 12h-4"/><path d="M12 12h-4"/></svg>),
            Logout: (props) => (<svg {...props} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="lucide lucide-log-out"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>),
        };

        const App = () => {
            const [customers, setCustomers] = useState([]);
            const [searchTerm, setSearchTerm] = useState('');
            const [currentTime, setCurrentTime] = useState(new Date());

            // Effect to fetch customer data from Firestore in real-time.
            useEffect(() => {
                if (!window.db || !window.auth || !window.userId) {
                    console.log("Waiting for Firebase to initialize...");
                    return;
                }
                const appId = typeof __app_id !== 'undefined' ? __app_id : 'default-app-id';
                const customersCollectionRef = window.firebase.collection(window.db, `artifacts/${appId}/users/${window.userId}/customers`);
                
                const unsubscribe = window.firebase.onSnapshot(customersCollectionRef, (snapshot) => {
                    const customersList = snapshot.docs.map(doc => ({
                        id: doc.id,
                        ...doc.data()
                    }));
                    setCustomers(customersList);
                }, (error) => {
                    console.error("Error fetching customers:", error);
                });

                return () => unsubscribe();
            }, []);

            // Effect for the live-updating clock.
            useEffect(() => {
                const timerId = setInterval(() => setCurrentTime(new Date()), 1000);
                return () => clearInterval(timerId);
            }, []);

            const filteredCustomers = customers.filter(customer =>
                customer.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                customer.email.toLowerCase().includes(searchTerm.toLowerCase())
            );

            const formatCurrentTime = (date) => {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            };

            return (
                <div className="min-h-screen bg-gray-100 p-4 sm:p-6 lg:p-8 font-['Inter']">
                    {/* Header */}
                    <header className="mb-8 p-4 bg-blue-600 rounded-2xl shadow-lg border-2 border-blue-700 text-white flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-extrabold">Customers</h1>
                            <p className="text-lg text-blue-100">Search through your customer list.</p>
                        </div>
                        <div className="flex items-center space-x-4">
                            <div className="flex items-center">
                                <icons.Clock className="w-5 h-5 mr-1" />
                                <span className="font-bold">{formatCurrentTime(currentTime)}</span>
                            </div>
                            <button
                                onClick={() => alert('Logging out...')}
                                className="p-2 bg-blue-700 rounded-full text-white hover:bg-blue-800 transition-colors duration-200"
                            >
                                <icons.Logout className="w-5 h-5" />
                            </button>
                        </div>
                    </header>

                    {/* Main Content */}
                    <main className="bg-white p-6 rounded-2xl shadow-lg border border-gray-200">
                        <div className="bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6">
                            <input
                                type="text"
                                placeholder="Search customers..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="w-full p-2 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                        <ul className="space-y-2">
                            {filteredCustomers.length > 0 ? (
                                filteredCustomers.map(customer => (
                                    <li key={customer.id} className="bg-gray-50 p-3 rounded-lg shadow-sm border border-gray-200">
                                        <p className="font-bold text-gray-900">{customer.name}</p>
                                        <p className="text-sm text-gray-500">{customer.email}</p>
                                    </li>
                                ))
                            ) : (
                                <p className="text-center text-gray-500 py-8">No customers found.</p>
                            )}
                        </ul>
                    </main>
                </div>
            );
        };
        root.render(<App />);
    </script>
</body>
</html>