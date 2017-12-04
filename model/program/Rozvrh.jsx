class Rozvrh extends React.Component{

  constructor(props) {
    super(props)
    
    this.aktivityVLiniich = this.props.data.linie
    .filter(lajna => lajna.poradi > 0)
    .map(lajna => {
      let aktivity = this.props.data.aktivity.filter(aktivita => aktivita.linie == lajna.id);
      return {
        linie: lajna.id,
        nazev: lajna.nazev,
        aktivity: aktivity
      };
    });
  }

  filtrujPodleLinie(poleAktivit) {
    return poleAktivit.filter(aktivita => {
      for (let i=0;i < this.props.zvoleneLinie.length;i++) {
        if (aktivita.linie == this.props.zvoleneLinie[i].id) {
          return true;
        }
      }
      return false;
    })
  }

  filtrujPodleDne(poleAktivit) {
    return poleAktivit.filter(aktivita => new Date(aktivita.zacatek).getDay() == this.props.zvolenyDen);
  }

  render() {
    let linie = this.filtrujPodleLinie(this.aktivityVLiniich).map(lajna => {
      let aktivity = this.filtrujPodleDne(lajna.aktivity);
      return <Lajna key = {lajna.linie} aktivity = {aktivity} nazev = {lajna.nazev[0].toUpperCase() + lajna.nazev.slice(1)} zvolTutoAktivitu = {this.props.zvolTutoAktivitu}/>
    });

    let casy = new Array(16).fill(null).map((item, index) => <th className = "tabulka-hlavicka-cas">{index + 8}</th>);
    let hlavickaNazvu = <th className = "tabulka-hlavicka-nazvu"></th>;
    casy.unshift(hlavickaNazvu);

    return (
      <table className = "tabulka">
        <thead>
          <tr>{casy}</tr>
        </thead>
        {linie}
      </table>
    );
  }
}
