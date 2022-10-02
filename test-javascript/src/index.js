import React from 'react';
import ReactDOM from 'react-dom/client';
import './bootstrap-utilities.css';
import './bootstrap.min.css';
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
    fetch("https://search-api.fie.future.net.uk/widget.php?id=review&site=TRD&model_name=iPad_Air",{
      // mode: 'no-cors',
      method: 'GET',
})
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
      console.log(data)

      return (
        <table class="table">
            <tr>
              <th scope="col"></th>
              <th scope="col">Dealer</th>
              <th scope="col">Name</th>
              <th scope="col">Price</th>
              <th scope="col">Link</th>
            </tr>
              {data.widget.data.offers.map(offer => (
                <tr>
                  <td><img src={offer.merchant.logo_url} alt='#'></img></td>
                  <td>{offer.merchant.name}</td>
                  <td>{offer.model}</td>
                  <td>{offer.offer.price} {offer.offer.currency_iso.toLowerCase()}</td>
                  <td class="offer-link"><a href={offer.offer.link}>lien vers l'offre</a></td>
                </tr>
              ))}
        </table>
      );
      
    }
  }
}

// ========================================

const root = ReactDOM.createRoot(document.getElementById("root"));
root.render(<FetchTheIpad />);
