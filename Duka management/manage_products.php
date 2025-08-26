<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Products</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <script src="https://unpkg.com/react@18/umd/react.development.js"></script>
  <script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
  <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
  <style>
    body { font-family: 'Inter', sans-serif; }
    .nav-buttons a {
        text-decoration: none;
        color: white;
    }
    .nav-buttons a:hover {
        color: #ddd;
    }
    .toast-container {
        z-index: 1000;
    }
  </style>
</head>
<body class="bg-light">

  <div id="root"></div>

  <script type="text/babel">
    const { useState, useEffect, useRef } = React;
    const root = ReactDOM.createRoot(document.getElementById('root'));

    const icons = {
      Cube: (props) => (<i className="fas fa-cube me-2" {...props}></i>),
      PlusCircle: (props) => (<i className="fas fa-plus-circle" {...props}></i>),
      Trash2: (props) => (<i className="fas fa-trash-alt me-2" {...props}></i>),
      Clock: (props) => (<i className="fas fa-clock me-2" {...props}></i>),
      Logout: (props) => (<i className="fas fa-sign-out-alt" {...props}></i>),
      ClipboardPen: (props) => (<i className="fas fa-clipboard-pen" {...props}></i>),
      DollarSign: (props) => (<i className="fas fa-dollar-sign me-2" {...props}></i>),
      Menu: (props) => (<i className="fas fa-bars" {...props}></i>),
      X: (props) => (<i className="fas fa-times" {...props}></i>),
    };

    const ConfirmationDialog = ({ show, title, message, onConfirmCombine, onConfirmSeparate, onCancel }) => {
      if (!show) return null;
      return (
        <div className="modal d-block" tabIndex="-1" role="dialog" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
          <div className="modal-dialog modal-dialog-centered" role="document">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title text-primary">{title}</h5>
                <button type="button" className="btn-close" onClick={onCancel} aria-label="Close"></button>
              </div>
              <div className="modal-body">
                <p>{message}</p>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-primary" onClick={onConfirmCombine}>Combine</button>
                <button type="button" className="btn btn-secondary" onClick={onConfirmSeparate}>Add Separately</button>
                <button type="button" className="btn btn-outline-danger" onClick={onCancel}>Cancel</button>
              </div>
            </div>
          </div>
        </div>
      );
    };

    const App = () => {
      const [products, setProducts] = useState([]);
      const [newProductName, setNewProductName] = useState('');
      const [newProductPriceInput, setNewProductPriceInput] = useState('');
      const [newProductQuantity, setNewProductQuantity] = useState('');
      const [currentTime, setCurrentTime] = useState(new Date());
      const [message, setMessage] = useState('');
      const [messageType, setMessageType] = useState('');
      const [totalAssetsValue, setTotalAssetsValue] = useState(0);
      const [showConfirmationDialog, setShowConfirmationDialog] = useState(false);
      const [pendingProductData, setPendingProductData] = useState(null);
      const [matchedProduct, setMatchedProduct] = useState(null);
      const [expandedDescriptions, setExpandedDescriptions] = useState({});
      const debounceTimeoutRef = useRef({}); 
      
      const calculatedTotal = (parseFloat(newProductPriceInput) || 0) * (parseInt(newProductQuantity) || 0);
      const SHOPKEEPER_ID = 1; 

      const showMessage = (msg, type) => {
        setMessage(msg);
        setMessageType(type);
        if (showMessage.timeoutId) {
            clearTimeout(showMessage.timeoutId);
        }
        showMessage.timeoutId = setTimeout(() => {
          setMessage('');
          setMessageType('');
        }, 3000);
      };

      const fetchProducts = async () => {
        try {
          const response = await fetch(`api.php?action=get_products&shopkeeper_id=${SHOPKEEPER_ID}`);
          const data = await response.json();
          if (data.success) {
            setProducts(data.products);
          } else {
            showMessage(data.message, 'danger');
          }
        } catch (e) {
          console.error('Error fetching products:', e);
          showMessage('Error fetching products.', 'danger');
        }
      };

      useEffect(() => {
        fetchProducts();
        const timerId = setInterval(() => setCurrentTime(new Date()), 1000);
        return () => clearInterval(timerId);
      }, []);

      useEffect(() => {
        const sum = products.reduce((acc, product) => acc + (parseFloat(product.total) || 0), 0);
        setTotalAssetsValue(sum);
      }, [products]);

      const handleAddProduct = async () => {
        const nameTrimmed = newProductName.trim();
        const priceParsed = parseFloat(newProductPriceInput); 
        const quantityParsed = parseInt(newProductQuantity);
        const totalCalculated = parseFloat(calculatedTotal.toFixed(2));

        if (!nameTrimmed || isNaN(priceParsed) || isNaN(quantityParsed) || priceParsed < 0 || quantityParsed <= 0) {
          showMessage('Please enter valid name, price (>=0), and quantity (>0)', 'danger');
          return;
        }

        setNewProductName('');
        setNewProductPriceInput('');
        setNewProductQuantity('');
        
        try {
          const checkResponse = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: nameTrimmed, action: 'check_duplicate', shopkeeper_id: SHOPKEEPER_ID }),
          });
          const checkData = await checkResponse.json();

          if (checkData.success && checkData.duplicate) {
            setMatchedProduct(checkData.product);
            setPendingProductData({
              name: nameTrimmed,
              price: priceParsed,
              quantity: quantityParsed,
              total: totalCalculated,
              shopkeeper_id: SHOPKEEPER_ID
            });
            setShowConfirmationDialog(true);
          } else {
            const addResponse = await fetch('api.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ 
                shopkeeper_id: SHOPKEEPER_ID,
                name: nameTrimmed,
                price: priceParsed,
                quantity: quantityParsed,
                total: totalCalculated,
              }),
            });
            const addData = await addResponse.json();
            if (addData.success) {
              showMessage('Product added successfully!', 'success');
              fetchProducts();
            } else {
              showMessage(addData.message, 'danger');
            }
          }
        } catch (e) {
          console.error('Error adding product:', e);
          showMessage('Error adding product.', 'danger');
        }
      };
      
      const closeConfirmationDialog = () => {
        setShowConfirmationDialog(false);
        setPendingProductData(null);
        setMatchedProduct(null);
      };

      const handleConfirmCombine = async () => {
        if (!matchedProduct || !pendingProductData) return;
        const newQuantity = matchedProduct.quantity + pendingProductData.quantity;
        const newTotal = parseFloat((parseFloat(matchedProduct.total) + pendingProductData.total).toFixed(2));
        
        try {
          const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              id: matchedProduct.id,
              quantity: newQuantity,
              total: newTotal,
              action: 'combine_update',
              shopkeeper_id: SHOPKEEPER_ID
            }),
          });
          const data = await response.json();
          if (data.success) {
            showMessage(data.message, 'success');
            fetchProducts();
          } else {
            showMessage(data.message, 'danger');
          }
        } catch (e) {
          console.error('Error combining product:', e);
          showMessage('Error combining product.', 'danger');
        } finally {
          closeConfirmationDialog();
        }
      };

      const handleConfirmSeparate = async () => {
        if (!pendingProductData) return;
        try {
          const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(pendingProductData),
          });
          const data = await response.json();
          if (data.success) {
            showMessage('Product added successfully!', 'success');
            fetchProducts();
          } else {
            showMessage(data.message, 'danger');
          }
        } catch (e) {
          console.error('Error adding product separately:', e);
          showMessage('Error adding product separately.', 'danger');
        } finally {
          closeConfirmationDialog();
        }
      };

      const handleConfirmCancel = () => {
        showMessage('Product addition cancelled.', 'info');
        closeConfirmationDialog();
      };
      
      const handleDeleteProduct = async (id) => {
        if (!window.confirm("Are you sure you want to delete this product?")) return;
        try {
          const response = await fetch(`api.php?id=${id}&shopkeeper_id=${SHOPKEEPER_ID}`, { method: 'DELETE' });
          const data = await response.json();
          if (data.success) {
            showMessage('Product deleted successfully!', 'info');
            fetchProducts();
          } else {
            showMessage(data.message, 'danger');
          }
        } catch (e) {
          console.error('Error deleting product:', e);
          showMessage('Error deleting product.', 'danger');
        }
      };

      const handleDescriptionChange = (productId, newDescription) => {
        setProducts(prevProducts =>
          prevProducts.map(p =>
            p.id === productId ? { ...p, description: newDescription } : p
          )
        );
        if (debounceTimeoutRef.current[productId]) {
          clearTimeout(debounceTimeoutRef.current[productId]);
        }
        debounceTimeoutRef.current[productId] = setTimeout(async () => {
          try {
            const response = await fetch('api.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                id: productId,
                description: newDescription.trim(),
                action: 'update_description',
                shopkeeper_id: SHOPKEEPER_ID
              }),
            });
            const data = await response.json();
            if (data.success) {
              showMessage('Description saved!', 'success');
            } else {
              showMessage(data.message, 'danger');
            }
          } catch (e) {
            console.error('Error saving description:', e);
            showMessage('Error saving description.', 'danger');
          }
        }, 700);
      };

      const toggleDescriptionVisibility = (productId) => {
        setExpandedDescriptions(prev => ({
          ...prev,
          [productId]: !prev[productId]
        }));
      };

      const formatCurrentTime = (date) => {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      };

      return (
        <>
          <ConfirmationDialog
            show={showConfirmationDialog}
            title={`Product "${pendingProductData?.name}" Already Exists`}
            message={`A product named "${pendingProductData?.name}" already exists with quantity ${matchedProduct?.quantity}. Do you want to combine these, add as a separate item, or cancel?`}
            onConfirmCombine={handleConfirmCombine}
            onConfirmSeparate={handleConfirmSeparate}
            onCancel={handleConfirmCancel}
          />

          <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
              <div class="container-fluid">
                  <a class="navbar-brand" href="index.php">
                      <i class="fas fa-store me-2"></i>DUKA Sales Portal
                  </a>
                  <div class="d-flex align-items-center">
                      <a href="salesstaff_dashboard.php" class="btn btn-outline-light me-3">
                          <i class="fas fa-tachometer-alt"></i> Dashboard
                      </a>
                      <div class="text-white me-3 d-none d-md-block">
                          <i class="fas fa-user-circle me-1"></i>
                          Sales Staff
                      </div>
                      <a href="logout.php" class="btn btn-outline-light btn-sm">
                          <i class="fas fa-sign-out-alt"></i> Logout
                      </a>
                  </div>
              </div>
          </nav>

          <div className="toast-container position-fixed bottom-0 end-0 p-3">
            {message && (
                <div className={`alert alert-dismissible fade show mb-0 alert-${messageType}`} role="alert">
                    {message}
                    <button type="button" className="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            )}
          </div>

          <div className="container mt-4">
              <div className="d-flex justify-content-between align-items-center mb-4">
                  <h2><i className="fas fa-cube me-2"></i> Manage Products</h2>
                  <div className="d-flex align-items-center text-primary fw-bold d-none d-md-block">
                      <icons.Clock />
                      <span>{formatCurrentTime(currentTime)}</span>
                  </div>
              </div>
              
              <div className="card mb-4 shadow-sm">
                  <div className="card-header bg-white text-primary fw-bold">
                      Add New Product
                  </div>
                  <div className="card-body">
                      <div className="row g-3 align-items-center">
                          <div className="col-12 col-md-4">
                              <input type="text" placeholder="Product Name" value={newProductName} onChange={(e) => setNewProductName(e.target.value)} className="form-control" />
                          </div>
                          <div className="col-6 col-md-2">
                              <input type="number" placeholder="Quantity" min="1" value={newProductQuantity} onChange={(e) => { const val = e.target.value; if (/^\d*$/.test(val)) setNewProductQuantity(val); }} className="form-control" />
                          </div>
                          <div className="col-6 col-md-3">
                              <input type="number" placeholder="Price Per Unit" min="0" step="0.01" value={newProductPriceInput} onChange={(e) => { const val = e.target.value; if (/^\d*\.?\d*$/.test(val)) setNewProductPriceInput(val); }} className="form-control" />
                          </div>
                          <div className="col-12 col-md-3 text-md-end">
                              {newProductName.trim() && calculatedTotal > 0 ? (
                                  <button onClick={handleAddProduct} className="btn btn-success w-100 d-flex align-items-center justify-content-center">
                                      <icons.PlusCircle /> Add Product
                                  </button>
                              ) : (
                                  <button disabled className="btn btn-light w-100 d-flex align-items-center justify-content-center">
                                      <icons.PlusCircle /> Add Product
                                  </button>
                              )}
                          </div>
                      </div>
                  </div>
              </div>

              <div className="card mb-4 shadow-sm bg-info text-white">
                  <div className="card-body d-flex justify-content-between align-items-center">
                      <h5 className="mb-0">
                          <icons.DollarSign />Total Inventory Value:
                      </h5>
                      <span className="h4 mb-0">KSH {totalAssetsValue.toFixed(2)}</span>
                  </div>
              </div>

              <div className="card shadow-sm">
                  <div className="card-header bg-white text-primary fw-bold">
                      Product List
                  </div>
                  <div className="card-body">
                      {products.length ? (
                          <ul className="list-group list-group-flush">
                              {products.map(product => (
                                  <li key={product.id} className="list-group-item d-flex justify-content-between align-items-center">
                                      <div>
                                          <h6 className="mb-1 text-primary fw-bold">{product.name}</h6>
                                          <small className="text-muted">
                                              Qty: {product.quantity} × KSH {parseFloat(product.price).toFixed(2)} ={' '}
                                              <span className="fw-bold text-dark">KSH {(parseFloat(product.total)).toFixed(2)}</span>
                                          </small>
                                      </div>
                                      <div className="d-flex align-items-center gap-2">
                                          <button onClick={() => handleDeleteProduct(product.id)} className="btn btn-outline-danger btn-sm" title="Delete Product">
                                              <icons.Trash2 />
                                          </button>
                                      </div>
                                  </li>
                              ))}
                          </ul>
                      ) : (
                          <p className="text-center text-muted p-4">No products found. Add a new one above!</p>
                      )}
                  </div>
              </div>
          </div>
        </>
      );
    };

    root.render(<App />);
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>