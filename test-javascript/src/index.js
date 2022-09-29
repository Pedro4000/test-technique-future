import React from 'react';
import ReactDOM from 'react-dom/client';
import './index.css';


class FetchTheIpad extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      error: null,
      isLoaded: false,
      data: []
    };
  }

  componentDidMount() {
    fetch("http://search-api.fie.future.net.uk/widget.php?id=review&site=TRD&model_id=iPad_Air")
      .then(res => res.json())
      .then(
        (data) => {
          this.setState({
            isLoaded: true,
            data: data
          });
        },
        // Note: it's important to handle errors here
        // instead of a catch() block so that we don't swallow
        // exceptions from actual bugs in components.
        (error) => {
          console.log(error);
          this.setState({
            isLoaded: true,
            error
          });
        }
      )
  }

  render() {
    const { error, isLoaded, data } = this.state;
    if (error) {
      return <div>Error: {error.message}</div>;
    } else if (!isLoaded) {
      return <div>Loading...</div>;
    } else {
      console.log(data.widget.data.offers)

      return (
        <ul>
          <p>liste des offres</p>
            {data.widget.data.offers.map(offer => (
              <li key={offer.id}>
              </li>
            ))}
        </ul>
      );
      
    }
  }
}

// ========================================

const root = ReactDOM.createRoot(document.getElementById("root"));
root.render(<FetchTheIpad />);
