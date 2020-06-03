class PublicSafety extends React.Component {
  textCapitalize = (s) => {
    let str = s.split(" ");
    for (let i = 0, x = str.length; i < x; i++) {
        str[i] = str[i].toLowerCase();
        str[i] = str[i][0].toUpperCase() + str[i].substr(1);
    }
    return str.join(" ");
  }

  render() {
    // Content for cards
    const contentPolice = [
      {
        heading: "Station",
        content: <div>
                    <div>{this.props.police_station_name}</div>
                    <div>{this.props.police_station_adress}</div>
                    <div>{this.props.police_station_neighborhood}, MA</div>
                    <div>{this.props.police_station_zip}</div>
                  </div>
      },
      {
        heading: "District",
        content: this.props.police_district
      },
      {
        content: <div>
                    Learn more about the City's <a href={"/departments/police"} className="mnl-link">Police Department</a>.
                    <spacefill></spacefill>
                 </div>
      }
    ]
    const contentFire = [
      {
        heading: "Station",
        content: <div>
                    <div>{this.textCapitalize(this.props.fire_station_name)}</div>
                    <div>{this.textCapitalize(this.props.fire_station_address)}</div>
                    <div>{this.textCapitalize(this.props.fire_station_neighborhood)}, MA</div>
                  </div>
      },
      {
        content: <div>
                    Learn more about the City's <a href={"/departments/fire-operations"} className="mnl-link">Fire Department</a>.
                    <spacefill></spacefill>
                 </div>
      }
    ];
    const secDesc = "Find the nearest police and fire stations to your address.";
    const cardsPublicSafety = (
      <div>
        <div className="sh">
          <h2 className="sh-title">Public Safety</h2>
        </div>
        <div className="supporting-text">
          <p>Find the nearest police and fire stations to your address.</p>
        </div>
        <div className="g">
          {/* Police Station */}
          <MnlCard
            title={"A Police Station Near You"}
            image_header={
              configProps.pathImage+"police.svg"
            }
            content_array={contentPolice}
          />
          {/* Fire Station */}
          <MnlCard
            title={"A Fire Station Near You"}
            image_header={
              configProps.pathImage+"fire_dept.svg"
            }
            content_array={contentFire}
          />
        </div>
        <button className="t--upper t--sans"
          onClick={() => {
            this.props.displaySection(null);
          }}
        >
          Back to results
        </button>
      </div>
    );

    let displayPublicSafety;
    if (this.props.section == "public_safety") {
      history.pushState(null, null, configProps.path+'?p3');
      displayPublicSafety = cardsPublicSafety;
    } else if (this.props.section == null) {
      displayPublicSafety = (
        <a
          className="cd g--4 g--4--sl m-t500 cdp-l mnl-section"
          title={"Public Safety"}
          style={{ textAlign: "left" }}
          onClick={() => {
            this.props.displaySection("public_safety");
          }}
        >
          <MnlSection
            title={"Public Safety"}
            image_header={
              configProps.pathImage+"first_aid.svg"
            }
            desc={secDesc}
          />
        </a>
      );
    } else {
      displayPublicSafety = null;
    }
    return displayPublicSafety;
  }
}