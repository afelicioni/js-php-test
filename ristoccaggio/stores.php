<?php
class StoresHelper
{
  private $stores = [];
  private $itemChoice;
    
  public function __construct($itemChoice)
  {
    $this->itemChoice = $itemChoice;

    $this->loadStores();
    $this->filterInUnderstock();
    $this->sort();
  }

  public function canBeRestocked()
  {
    return $this->stores;
  }

  private function loadStores()
  {
    $this->stores = json_decode(file_get_contents('./stores.json'), true);
  }

  private function callableStoreFilterItem($store) {
    return count(array_filter($store['items'], function ($item) {
      if ($item['id']===$this->itemChoice && $item['qty']<$item['minQty']) {
        return true;
      }
      return false;
    })) > 0;
  }

  private function filterInUnderstock()
  {
    $this->stores = array_filter($this->stores, array($this, 'callableStoreFilterItem'));
  }

  private function sort()
  {
    usort($this->stores, function($a, $b) {
      if ($a['distance'] < $b['distance']) {
        return -1;
      } elseif ($a['distance'] == $b['distance']) {
        $a_qty = array_shift(array_filter($a['items'], function ($item) { return $item['id']===$this->itemChoice; }));
        $b_qty = array_shift(array_filter($b['items'], function ($item) { return $item['id']===$this->itemChoice; }));
        $a_qty = (!is_null($a_qty)) ? $a_qty['qty'] : null;
        $b_qty = (!is_null($b_qty)) ? $b_qty['qty'] : null;
        if (!is_null($a_qty) && !is_null($b_qty) && $a_qty < $b_qty) {
          return -1;
        }
      }
      return 1;
    });
  }
}

$itemChoice = (int) $_GET['item'];
$oStores = new StoresHelper($itemChoice);
$storesCanBeRestocked = $oStores->canBeRestocked();

$emptyCard = function() {
  return <<<HEREDOC
    <div class="card text-white bg-secondary">
      <div class="card-body">
        <h6 class="card-title">Nessun magazzino da rifornire</h6>
        <p class="card-text">Effettua una richiesta diversa</p>
      </div>
    </div>
HEREDOC;
};

$storeCard = function($storeData) use ($itemChoice) {
  return <<<HEREDOC
    <div class="card">
    <div class="card-body">
      <div class="card-text">

        <div class="row">
          <div class="col-sm">
            <h6>${storeData['name']}</h6>
          </div>
          <div class="col-sm">
            Distanza <span class="h6">${storeData['distance']}km</span>
          </div>
          <div class="col-sm text-right align-self-center">
            <a class="btn btn-primary btn-cta store-choice" href="#" role="button" data-item="${itemChoice}" data-store="${storeData['id']}">${storeData['name']} CTA</a>
          </div>
        </div>

      </div>
    </div>
    </div>
HEREDOC;
};

?><!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="progetto.css">
    <title>PROGETTO</title>
  </head>
  <body id="pagina-stores">
    <div class="container">
    <h1 class="text-primary">GestioneMagazzini</h1>
    <h2>Gestione ristoccaggio magazzino</h2>

    <h4>Risultati</h4>
    
    <div id="progetto-stores-container">
      <?php
      $cardsStoresCanBeRestocked = array_map($storeCard, $storesCanBeRestocked);
      echo count($cardsStoresCanBeRestocked) > 0 ? implode('', $cardsStoresCanBeRestocked) : call_user_func($emptyCard);
      ?>
    </div>

    <div class="modal fade" id="modalStore" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header justify-content-center">
            <h5 class="modal-title text-primary">Azione Confermata</h5>
          </div>
          <div class="modal-body d-flex justify-content-center">
          </div>
          <div class="modal-footer justify-content-center">
            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Chiudi</button>
          </div>
        </div>
      </div>
    </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script type="text/babel">
    (($) => {
      var [ items, stores ] = [ [], [] ];

      const modalBody = (item, store, qty) => {
        return `
          <div>
            <span class="text-secondary">Prodotto:</span> ${item}<br>
            <span class="text-secondary">Magazzino:</span> ${store}<br>
            <span class="text-secondary">Articoli inviati:</span> ${qty}
          </div>
          `;
      };

      $(document).on('click', '.store-choice', function(e) {
        e.preventDefault();
        const itemId = Number($(this).attr('data-item'));
        const storeId = Number($(this).attr('data-store'));
        const [ item ] = items.filter(item => item.id===itemId);
        const [ store ] = stores.filter(store => store.id===storeId);
        const [ storeItem ] = store.items.filter(item => item.id===itemId);
        const qty = storeItem.minQty - storeItem.qty;

        $('#modalStore .modal-body').html(modalBody(item.name, store.name, qty));
        $('#modalStore').modal('show');
      });

      $(function() {
        const urls = ['items.json', 'stores.json'];
        Promise.all(urls.map(url =>
          fetch(url).then(response => response.json())
        )).then(members => {
          [ items, stores ] = members;
        });

      });
    })(jQuery);
    </script>
  </body>
</html>




